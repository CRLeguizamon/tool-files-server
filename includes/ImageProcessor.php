<?php
/**
 * Servicio de Procesamiento, Redimensionamiento y Optimización de Imágenes
 * Compatible con Imagick y GD (JPG, PNG, WEBP, AVIF)
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';

class ImageProcessor
{
    /**
     * Convierte un texto a slug limpio:
     * - Quita acentos y diacríticos (ó -> o, ñ -> n, etc.)
     * - Convierte a minúsculas
     * - Reemplaza espacios y símbolos por guiones '-'
     * - Limpia guiones repetidos y en los extremos
     *
     * @param string $text
     * @return string
     */
    public static function slugify(string $text): string
    {
        // Reemplazo explícito de acentos y caracteres especiales del español y latín
        $accents = [
            'á'=>'a', 'é'=>'e', 'í'=>'i', 'ó'=>'o', 'ú'=>'u', 'ü'=>'u', 'ñ'=>'n',
            'Á'=>'a', 'É'=>'e', 'Í'=>'i', 'Ó'=>'o', 'Ú'=>'u', 'Ü'=>'u', 'Ñ'=>'n',
            'à'=>'a', 'è'=>'e', 'ì'=>'i', 'ò'=>'o', 'ù'=>'u',
            'ä'=>'a', 'ë'=>'e', 'ï'=>'i', 'ö'=>'o',
            'â'=>'a', 'ê'=>'e', 'î'=>'i', 'ô'=>'o', 'û'=>'u',
            'ç'=>'c', 'Ç'=>'c'
        ];
        $text = strtr($text, $accents);

        // Si la extensión intl está disponible, transliterar caracteres adicionales
        if (class_exists('Transliterator')) {
            $trans = \Transliterator::create('Any-Latin; Latin-ASCII');
            if ($trans) {
                $text = $trans->transliterate($text);
            }
        } elseif (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
            if ($converted !== false) {
                $text = $converted;
            }
        }

        // Convertir a minúsculas UTF-8
        $text = mb_strtolower($text, 'UTF-8');

        // Reemplazar todo lo que no sea a-z o 0-9 por guion
        $text = preg_replace('/[^a-z0-9]+/i', '-', $text);

        // Eliminar guiones duplicados y de los extremos
        $text = trim((string) $text, '-');

        return $text !== '' ? $text : 'imagen';
    }

    /**
     * Comprueba si un nombre de archivo ya existe registrado en la base de datos
     *
     * @param string $filename
     * @return bool
     */
    private static function nameExistsInDb(string $filename): bool
    {
        try {
            if (function_exists('get_db')) {
                $pdo = get_db();
                $stmt = $pdo->prepare("SELECT `id` FROM `media` WHERE `filename` = ? LIMIT 1");
                $stmt->execute([$filename]);
                return (bool) $stmt->fetch();
            }
        } catch (\Throwable $e) {
            // Si la BD no está disponible temporalmente, continúa
        }
        return false;
    }

    /**
     * Genera un nombre de archivo único con el formato:
     * - Primera vez: hola-como-estas.extension
     * - Si se repite: hola-como-estas-1.extension, hola-como-estas-2.extension, etc.
     *
     * @param string $slug
     * @param string $ext
     * @param string|null $customDir Si se especifica, comprueba colisiones únicamente en esa carpeta (modo ZIP temporal)
     * @return string
     */
    public static function generateUniqueFilename(string $slug, string $ext, ?string $customDir = null): string
    {
        $candidate = "{$slug}.{$ext}";

        if ($customDir !== null) {
            if (!file_exists($customDir . '/' . $candidate)) {
                return $candidate;
            }
            $counter = 1;
            while (file_exists($customDir . "/{$slug}-{$counter}.{$ext}")) {
                $counter++;
            }
            return "{$slug}-{$counter}.{$ext}";
        }

        // Comprobación inicial: si no existe en uploads ni en BD, se usa tal cual
        if (!file_exists(UPLOAD_DIR . '/' . $candidate) && !self::nameExistsInDb($candidate)) {
            return $candidate;
        }

        // Si ya existe, agregar número progresivo (-1, -2, -3...)
        $counter = 1;
        while (
            file_exists(UPLOAD_DIR . "/{$slug}-{$counter}.{$ext}") ||
            self::nameExistsInDb("{$slug}-{$counter}.{$ext}")
        ) {
            $counter++;
        }

        return "{$slug}-{$counter}.{$ext}";
    }

    /**
     * Procesa una imagen subida: valida, formatea nombre, redimensiona, comprime y opcionalmente convierte a JPG
     *
     * @param array $fileInput $_FILES['image']
     * @param int $maxWidth Ancho máximo permitido (no agranda imágenes menores)
     * @param bool $compress Si debe aplicar compresión agresiva (< 1 MB)
     * @param bool $convertToJpg Si debe convertir a JPG (si es JPG se ignora, si es PNG se cambia)
     * @param string|null $customOutputDir Carpeta temporal personalizada (para lotes ZIP sin guardar en servidor)
     * @param bool $generateThumb Si debe generar miniatura (false en modo ZIP temporal)
     * @return array
     */
    public static function process(
        array $fileInput, 
        int $maxWidth = DEFAULT_MAX_WIDTH, 
        bool $compress = true,
        bool $convertToJpg = false,
        ?string $customOutputDir = null,
        bool $generateThumb = true
    ): array {
        // 1. Validaciones básicas del archivo subido
        $isUploaded = is_uploaded_file($fileInput['tmp_name'])
            || (PHP_SAPI === 'cli' && file_exists($fileInput['tmp_name']));

        if (!isset($fileInput['tmp_name']) || !$isUploaded) {
            throw new InvalidArgumentException('No se ha subido ningún archivo válido.');
        }

        if ($fileInput['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Error al recibir el archivo en el servidor. Código: ' . $fileInput['error']);
        }

        $originalSize = (int) $fileInput['size'];
        if ($originalSize > MAX_UPLOAD_SIZE_BYTES) {
            throw new InvalidArgumentException(sprintf('El archivo excede el tamaño máximo permitido de %d MB.', MAX_UPLOAD_SIZE_BYTES / (1024 * 1024)));
        }

        $tmpPath = $fileInput['tmp_name'];
        $originalName = basename($fileInput['name']);
        $rawExt = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        // 2. Validación estricta de MIME Type mediante finfo
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $tmpPath) ?: '';
        finfo_close($finfo);

        if (!in_array($mime, ALLOWED_MIMES, true)) {
            app_log('Intento de subida con tipo MIME no permitido', ['mime' => $mime, 'file' => $originalName]);
            throw new InvalidArgumentException("Formato no permitido ({$mime}). Solo se aceptan JPG, PNG, WEBP y AVIF.");
        }

        // Extensión original deducida del tipo MIME
        $origExt = match ($mime) {
            'image/jpeg', 'image/pjpeg' => 'jpg',
            'image/png', 'image/x-png'   => 'png',
            'image/webp'                 => 'webp',
            'image/avif'                 => 'avif',
            default                      => $rawExt,
        };

        // 3. Determinar formato final:
        // Si se sube JPG se ignora la conversión. Si se sube PNG (u otro formato) y se activa la opción, se convierte a JPG.
        $isOriginalJpg = in_array($origExt, ['jpg', 'jpeg'], true);
        $shouldConvertToJpg = $convertToJpg && !$isOriginalJpg;
        $targetExt = $shouldConvertToJpg ? 'jpg' : $origExt;
        $targetMime = ($targetExt === 'jpg') ? 'image/jpeg' : $mime;

        // 4. Ajuste de ancho máximo permitido (mínimo 50px, máximo 2048px)
        $maxWidth = max(MIN_ALLOWED_WIDTH, min(MAX_ALLOWED_WIDTH, $maxWidth));

        // 5. Asegurar existencia de carpetas de destino
        $targetDir = $customOutputDir !== null ? rtrim($customOutputDir, '/\\') : UPLOAD_DIR;
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0755, true);
        }
        if ($generateThumb && $customOutputDir === null && !is_dir(THUMB_DIR)) {
            @mkdir(THUMB_DIR, 0755, true);
        }

        // 6. Formateo estricto del nombre de la imagen:
        // - Quita acentos y espacios: hola como estas -> hola-como-estas
        // - Solo si se repite agrega número progresivo: hola-como-estas-1.extension
        $rawBaseName = pathinfo($originalName, PATHINFO_FILENAME);
        $slug = self::slugify($rawBaseName);
        $uniqueFilename = self::generateUniqueFilename($slug, $targetExt, $customOutputDir);

        $destPath = $targetDir . '/' . $uniqueFilename;
        $thumbPath = ($generateThumb && $customOutputDir === null) ? (THUMB_DIR . '/' . $uniqueFilename) : null;

        app_log('Iniciando procesamiento de imagen', [
            'original'        => $originalName,
            'slug'            => $slug,
            'archivo_final'   => $uniqueFilename,
            'formato_orig'    => $origExt,
            'formato_dest'    => $targetExt,
            'convertido_jpg'  => $shouldConvertToJpg,
            'max_width'       => $maxWidth,
            'compress'        => $compress,
            'modo_temporal'   => $customOutputDir !== null,
        ]);

        // 7. Procesar usando Imagick o GD
        $useImagick = extension_loaded('imagick') && class_exists('Imagick');
        $processed = false;

        if ($useImagick) {
            try {
                $processed = self::processWithImagick(
                    $tmpPath, 
                    $destPath, 
                    $thumbPath, 
                    $maxWidth, 
                    $compress, 
                    $targetExt, 
                    $shouldConvertToJpg
                );
            } catch (\Throwable $e) {
                app_log('Imagick falló, intentando con GD como fallback', ['error' => $e->getMessage()]);
                $useImagick = false;
            }
        }

        if (!$processed) {
            $processed = self::processWithGD(
                $tmpPath, 
                $destPath, 
                $thumbPath, 
                $maxWidth, 
                $compress, 
                $targetExt, 
                $shouldConvertToJpg
            );
        }

        clearstatcache(true, $destPath);
        $finalSize = filesize($destPath);
        $savedBytes = max(0, $originalSize - $finalSize);
        $savedPercent = $originalSize > 0 ? round(($savedBytes / $originalSize) * 100, 1) : 0;

        app_log('Procesamiento completado con éxito', [
            'archivo'       => $uniqueFilename,
            'tamaño_orig'   => $originalSize,
            'tamaño_final'  => $finalSize,
            'ahorro_pct'    => $savedPercent . '%',
            'dimensiones'   => "{$processed['width']}x{$processed['height']}",
            'motor'         => $useImagick ? 'Imagick' : 'GD',
            'modo_temporal' => $customOutputDir !== null,
        ]);

        return [
            'success'          => true,
            'filename'         => $uniqueFilename,
            'original_name'    => $originalName,
            'file_path'        => $customOutputDir !== null ? '' : ('uploads/' . $uniqueFilename),
            'thumb_path'       => $thumbPath !== null ? ('uploads/thumbs/' . $uniqueFilename) : '',
            'file_url'         => $customOutputDir !== null ? '' : base_url('uploads/' . $uniqueFilename),
            'thumb_url'        => $thumbPath !== null ? base_url('uploads/thumbs/' . $uniqueFilename) : '',
            'file_size'        => $finalSize,
            'original_size'    => $originalSize,
            'saved_bytes'      => $savedBytes,
            'saved_percent'    => $savedPercent,
            'width'            => $processed['width'],
            'height'           => $processed['height'],
            'mime_type'        => $targetMime,
            'is_compressed'    => $compress ? 1 : 0,
            'is_converted_jpg' => $shouldConvertToJpg ? 1 : 0,
        ];
    }

    /**
     * Procesamiento de imagen con Imagick
     */
    private static function processWithImagick(
        string $src,
        string $dest,
        ?string $thumbDest,
        int $maxWidth,
        bool $compress,
        string $targetExt,
        bool $convertedToJpg
    ): array {
        $im = new Imagick($src);

        // Auto-orientar si hay metadatos EXIF de orientación
        if (method_exists($im, 'autoOrient')) {
            $im->autoOrient();
        }

        $origWidth = $im->getImageWidth();
        $origHeight = $im->getImageHeight();

        // NUNCA agrandar: si la imagen es menor o igual al ancho máximo, mantiene sus dimensiones intactas
        $newWidth = min($origWidth, $maxWidth);
        $newHeight = ($newWidth < $origWidth)
            ? (int) round($origHeight * ($newWidth / max(1, $origWidth)))
            : $origHeight;

        // Solo redimensionar si el ancho original superaba el máximo
        if ($newWidth < $origWidth) {
            $im->resizeImage($newWidth, $newHeight, Imagick::FILTER_LANCZOS, 1);
        }

        // Manejo específico por formato de destino para máxima optimización
        if ($targetExt === 'jpg') {
            // Si la imagen original tiene transparencia o proviene de PNG/WebP,
            // componer sobre fondo blanco sólido para evitar que las áreas transparentes queden negras
            if ($convertedToJpg || $im->getImageAlphaChannel()) {
                $im->setImageBackgroundColor('white');
                if (method_exists($im, 'mergeImageLayers')) {
                    $im = $im->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
                } elseif (method_exists($im, 'flattenImages')) {
                    $im = $im->flattenImages();
                }
            }

            $im->setImageFormat('jpeg');
            $im->setImageCompression(Imagick::COMPRESSION_JPEG);
            $im->setSamplingFactors(['2x2', '1x1', '1x1']); // 4:2:0 subsampling esencial para compresión web
            $im->setInterlaceScheme(Imagick::INTERLACE_JPEG); // JPEG progresivo

            if ($compress) {
                $im->stripImage(); // Eliminar perfiles ICC pesados, EXIF y thumbnails incrustados
                $im->setImageCompressionQuality(76); // Calidad óptima para que quede holgadamente < 1 MB
            } else {
                $im->setImageCompressionQuality(90);
            }
        } elseif ($targetExt === 'png') {
            $im->setImageFormat('png');
            if ($compress) {
                $im->stripImage();
                $im->setOption('png:compression-level', '9');
                $im->setOption('png:compression-filter', '5');
                $im->setOption('png:compression-strategy', '1');
            }
        } elseif ($targetExt === 'webp') {
            $im->setImageFormat('webp');
            if ($compress) {
                $im->stripImage();
                $im->setImageCompressionQuality(78);
            } else {
                $im->setImageCompressionQuality(90);
            }
        } elseif ($targetExt === 'avif') {
            $im->setImageFormat('avif');
            if ($compress) {
                $im->stripImage();
                $im->setImageCompressionQuality(72);
            } else {
                $im->setImageCompressionQuality(85);
            }
        }

        $im->writeImage($dest);

        // Generar miniatura (máx 260px, NUNCA agrandar imágenes que midan menos de 260px)
        if ($thumbDest !== null && $thumbDest !== '') {
            $thumb = clone $im;
            $thumbWidth = min(260, $newWidth);
            $thumbHeight = ($thumbWidth < $newWidth)
                ? (int) round($newHeight * ($thumbWidth / max(1, $newWidth)))
                : $newHeight;

            if ($thumbWidth < $newWidth) {
                $thumb->resizeImage($thumbWidth, $thumbHeight, Imagick::FILTER_TRIANGLE, 1);
            }
            $thumb->stripImage();
            $thumb->setImageCompressionQuality(75);
            $thumb->writeImage($thumbDest);
            $thumb->clear();
            $thumb->destroy();
        }

        $im->clear();
        $im->destroy();

        return [
            'width'  => $newWidth,
            'height' => $newHeight,
        ];
    }

    /**
     * Procesamiento de imagen con GD
     */
    private static function processWithGD(
        string $src,
        string $dest,
        ?string $thumbDest,
        int $maxWidth,
        bool $compress,
        string $targetExt,
        bool $convertedToJpg
    ): array {
        if (!extension_loaded('gd')) {
            throw new RuntimeException('La extensión GD no está disponible en este servidor.');
        }

        $imageInfo = @getimagesize($src);
        if (!$imageInfo) {
            throw new RuntimeException('No se pudieron leer las dimensiones de la imagen con GD.');
        }

        $origWidth = $imageInfo[0];
        $origHeight = $imageInfo[1];
        $imageType = $imageInfo[2];

        // Crear recurso GD según el formato original
        $srcImage = match ($imageType) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($src),
            IMAGETYPE_PNG  => @imagecreatefrompng($src),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($src) : null,
            defined('IMAGETYPE_AVIF') ? IMAGETYPE_AVIF : -999 => function_exists('imagecreatefromavif') ? @imagecreatefromavif($src) : null,
            default        => null,
        };

        if (!$srcImage) {
            $rawContent = file_get_contents($src);
            $srcImage = @imagecreatefromstring($rawContent);
        }

        if (!$srcImage) {
            throw new RuntimeException("No se pudo cargar la imagen para procesarla con GD (tipo {$imageType}).");
        }

        // NUNCA agrandar: si la imagen es menor o igual al ancho máximo, mantiene sus dimensiones originales intactas
        $newWidth = min($origWidth, $maxWidth);
        $newHeight = ($newWidth < $origWidth)
            ? (int) round($origHeight * ($newWidth / max(1, $origWidth)))
            : $origHeight;

        $dstImage = imagecreatetruecolor($newWidth, $newHeight);

        // Si el destino es JPG (por ejemplo, PNG convertido a JPG), aplicar fondo blanco para que la transparencia no quede negra
        if ($targetExt === 'jpg') {
            $white = imagecolorallocate($dstImage, 255, 255, 255);
            imagefilledrectangle($dstImage, 0, 0, $newWidth, $newHeight, $white);
        } elseif ($targetExt === 'png' || $targetExt === 'webp') {
            // Preservar transparencia para PNG y WebP
            imagealphablending($dstImage, false);
            imagesavealpha($dstImage, true);
            $transparent = imagecolorallocatealpha($dstImage, 255, 255, 255, 127);
            imagefilledrectangle($dstImage, 0, 0, $newWidth, $newHeight, $transparent);
        }

        imagecopyresampled($dstImage, $srcImage, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);

        // Guardar imagen optimizada
        self::saveGDImage($dstImage, $dest, $targetExt, $compress ? DEFAULT_COMPRESS_QUALITY : 90);

        // Generar Thumbnail (máx 260px, NUNCA agrandar imágenes menores a 260px)
        if ($thumbDest !== null && $thumbDest !== '') {
            $tWidth = min(260, $newWidth);
            $tHeight = ($tWidth < $newWidth)
                ? (int) round($newHeight * ($tWidth / max(1, $newWidth)))
                : $newHeight;
            $tImage = imagecreatetruecolor($tWidth, $tHeight);

            if ($targetExt === 'jpg') {
                $tWhite = imagecolorallocate($tImage, 255, 255, 255);
                imagefilledrectangle($tImage, 0, 0, $tWidth, $tHeight, $tWhite);
            } elseif ($targetExt === 'png' || $targetExt === 'webp') {
                imagealphablending($tImage, false);
                imagesavealpha($tImage, true);
                $transparent = imagecolorallocatealpha($tImage, 255, 255, 255, 127);
                imagefilledrectangle($tImage, 0, 0, $tWidth, $tHeight, $transparent);
            }

            imagecopyresampled($tImage, $dstImage, 0, 0, 0, 0, $tWidth, $tHeight, $newWidth, $newHeight);
            self::saveGDImage($tImage, $thumbDest, $targetExt, 75);
            imagedestroy($tImage);
        }

        imagedestroy($srcImage);
        imagedestroy($dstImage);

        return [
            'width'  => $newWidth,
            'height' => $newHeight,
        ];
    }

    /**
     * Guarda el recurso de imagen GD con compresión adecuada
     */
    private static function saveGDImage(\GdImage $img, string $dest, string $ext, int $quality): void
    {
        switch ($ext) {
            case 'jpg':
            case 'jpeg':
                imagejpeg($img, $dest, $quality);
                break;
            case 'png':
                // PNG quality en GD va de 0 (sin compresión) a 9 (máxima compresión)
                imagepng($img, $dest, 9);
                break;
            case 'webp':
                if (function_exists('imagewebp')) {
                    imagewebp($img, $dest, $quality);
                } else {
                    imagejpeg($img, $dest, $quality);
                }
                break;
            case 'avif':
                if (function_exists('imageavif')) {
                    imageavif($img, $dest, $quality);
                } elseif (function_exists('imagewebp')) {
                    imagewebp($img, $dest, $quality);
                } else {
                    imagejpeg($img, $dest, $quality);
                }
                break;
            default:
                imagejpeg($img, $dest, $quality);
                break;
        }
    }
}
