<?php
class Settings
{
    private \PDO $pdo;
    private string $assetsDir;
    private string $assetsUrlBase;

    public function __construct(PDO $pdo, string $assetsDir = null, string $assetsUrlBase = null)
    {
        $this->pdo = $pdo;
        $this->assetsDir = $assetsDir ?? __DIR__ . '/../assets/brand';
        $this->assetsUrlBase = $assetsUrlBase ?? '/assets/brand';
        if (!is_dir($this->assetsDir)) @mkdir($this->assetsDir, 0775, true);
    }

    public function load(): array
    {
        $defaults = [
            'site_name'     => 'GeoLite',
            'primary_color' => '#667eea',
            'footer_text'   => '© ' . date('Y') . ' GeoLite',
            'logo_url'      => null,
            'hero_image'    => null,
        ];
        $db = $this->pdo->query("SELECT site_name, primary_color, footer_text, logo_url, hero_image FROM app_settings WHERE id = 1")
                        ->fetch(PDO::FETCH_ASSOC) ?: [];

        // Optional file fallback if you keep settings.php
        $file = __DIR__ . '/../settings.php';
        $fileArr = file_exists($file) ? include $file : [];

        return array_merge($defaults, array_filter($db), is_array($fileArr) ? array_filter($fileArr) : []);
    }

    /** Returns [settings, errors] */
    public function save(array $post, array $files): array
    {
        $errors = [];
        $current = $this->load();

        $site_name     = trim($post['site_name'] ?? $current['site_name']);
        $primary_color = trim($post['primary_color'] ?? $current['primary_color']);
        $primary_color = $primary_color[0] === '#' ? $primary_color : '#'.$primary_color;
        $footer_text   = trim($post['footer_text'] ?? $current['footer_text']);

        if ($site_name === '') $errors[] = 'Site name is required.';
        if (!preg_match('/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6}|[A-Fa-f0-9]{8})$/', $primary_color))
            $errors[] = 'Primary color must be a valid hex color (e.g., #667eea).';

        $logo_url = $current['logo_url'];
        $hero_url = $current['hero_image'];

        if (!empty($files['logo']['name'])) {
            $res = $this->processImageUpload($files['logo'], 'logo');
            if (isset($res['error'])) $errors[] = $res['error']; else $logo_url = $res['url'];
        }
        if (!empty($files['hero_image']['name'])) {
            $res = $this->processImageUpload($files['hero_image'], 'hero');
            if (isset($res['error'])) $errors[] = $res['error']; else $hero_url = $res['url'];
        }

        if (!empty($post['delete_logo']) && $logo_url) { $this->unlinkIfLocal($logo_url); $logo_url = null; }
        if (!empty($post['delete_hero']) && $hero_url) { $this->unlinkIfLocal($hero_url); $hero_url = null; }

        if ($errors) {
            return [[
                'site_name'=>$site_name,'primary_color'=>$primary_color,'footer_text'=>$footer_text,
                'logo_url'=>$logo_url,'hero_image'=>$hero_url
            ], $errors];
        }

        // PostgreSQL UPSERT
        $sql = "INSERT INTO app_settings (id, site_name, primary_color, footer_text, logo_url, hero_image, updated_at)
                VALUES (1, :site_name, :primary_color, :footer_text, :logo_url, :hero_image, CURRENT_TIMESTAMP)
                ON CONFLICT (id) DO UPDATE SET
                    site_name = EXCLUDED.site_name,
                    primary_color = EXCLUDED.primary_color,
                    footer_text = EXCLUDED.footer_text,
                    logo_url = EXCLUDED.logo_url,
                    hero_image = EXCLUDED.hero_image,
                    updated_at = CURRENT_TIMESTAMP";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':site_name'     => $site_name,
            ':primary_color' => $primary_color,
            ':footer_text'   => $footer_text,
            ':logo_url'      => $logo_url,
            ':hero_image'    => $hero_url
        ]);

        return [$this->load(), []];
    }

    private function processImageUpload(array $file, string $prefix): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK)
            return ['error' => 'Upload error for ' . $prefix];

        $tmp = $file['tmp_name'];
        $finfo = @getimagesize($tmp);
        if (!$finfo) return ['error' => 'File for ' . $prefix . ' is not a valid image.'];

        $mime = $finfo['mime'] ?? '';
        $ext = match ($mime) {
            'image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp', 'image/svg+xml' => 'svg',
            default => null,
        };
        if (!$ext) return ['error' => 'Unsupported image type for ' . $prefix . ' (png, jpg, webp, svg).'];

        $name = $prefix . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $dest = rtrim($this->assetsDir, '/\\') . DIRECTORY_SEPARATOR . $name;

        if (!move_uploaded_file($tmp, $dest))
            return ['error' => 'Failed to store uploaded ' . $prefix . '.'];

        $url = rtrim($this->assetsUrlBase, '/') . '/' . $name;
        return ['url' => $url];
    }

    private function unlinkIfLocal(string $url): void
    {
        $path = parse_url($url, PHP_URL_PATH);
        $candidate = rtrim($this->assetsDir, '/\\') . DIRECTORY_SEPARATOR . basename($path);
        if (strpos($candidate, rtrim($this->assetsDir, '/\\')) === 0 && file_exists($candidate)) {
            @unlink($candidate);
        }
    }
}
