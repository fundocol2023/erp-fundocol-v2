<?php

if (!function_exists('erp_app_url')) {
    function erp_app_url(string $path = ''): string
    {
        return '/' . ltrim($path, '/');
    }
}

if (!function_exists('erp_asset_url')) {
    function erp_asset_url(string $path): string
    {
        $relativePath = ltrim($path, '/');
        $fullPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $version = is_file($fullPath) ? (string) filemtime($fullPath) : null;
        $url = erp_app_url($relativePath);

        return $version ? $url . '?v=' . $version : $url;
    }
}

if (!function_exists('erp_brand_suffix')) {
    function erp_brand_suffix(): string
    {
        return 'ERP Fundocol';
    }
}

if (!function_exists('erp_request_looks_like_html')) {
    function erp_request_looks_like_html(): bool
    {
        if (PHP_SAPI === 'cli') {
            return false;
        }

        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        return in_array($method, ['GET', 'HEAD'], true);
    }
}

if (!function_exists('erp_favicon_tag')) {
    function erp_favicon_tag(): string
    {
        $href = htmlspecialchars(erp_asset_url('assets/img/Fundocol_favicon.png'), ENT_QUOTES, 'UTF-8');

        return '<link rel="icon" type="image/x-icon" href="' . $href . '">';
    }
}

if (!function_exists('erp_normalize_title_text')) {
    function erp_normalize_title_text(string $title): string
    {
        $base = trim(html_entity_decode(strip_tags($title), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($base === '') {
            return erp_brand_suffix();
        }

        do {
            $previous = $base;
            $base = preg_replace('/\s*(?:\||-|–|—)\s*(?:ERP(?:\s+Fundocol)?|Fundocol)\s*$/iu', '', $base) ?? $base;
            $base = trim($base);
        } while ($base !== '' && $base !== $previous);

        $aliases = [
            'login' => 'Iniciar sesión',
            'módulo de compras' => 'Compras',
            'modulo de compras' => 'Compras',
            'módulo de usuarios' => 'Usuarios y permisos',
            'modulo de usuarios' => 'Usuarios y permisos',
            'inventarios - submenú' => 'Inventarios',
            'inventarios - submenu' => 'Inventarios',
            'panel general de solicitudes' => 'Panel general de solicitudes',
            'pendientes firma documentos' => 'Pendientes de firma de documentos',
            'firma de documentos' => 'Firma de documentos',
            'firmar documento' => 'Firmar documento',
            'revisar solicitud de vehiculo' => 'Revisar solicitud de vehículo',
            'revisar solicitud de vehículo' => 'Revisar solicitud de vehículo',
            'solicitar vehículo' => 'Solicitar vehículo',
            'historial de solicitudes (fundocol + consorcios)' => 'Historial de solicitudes',
            'línea de tiempo' => 'Línea de tiempo',
            'linea de tiempo' => 'Línea de tiempo',
            'inventario de equipo de computo' => 'Inventario de equipos de cómputo',
            'inventario de equipo de cómputo' => 'Inventario de equipos de cómputo',
            'nueva asignacion' => 'Nueva asignación',
            'nueva asignación' => 'Nueva asignación',
            'items y equipos' => 'Ítems y equipos',
            'revisar compra fija consorcio (contabilidad)' => 'Revisar compra fija de consorcio (Contabilidad)',
            'revisar compra fija consorcio (direccion)' => 'Revisar compra fija de consorcio (Dirección)',
            'revisar compra fija consorcio (dirección)' => 'Revisar compra fija de consorcio (Dirección)',
            'revisar compra fija consorcio (presupuesto)' => 'Revisar compra fija de consorcio (Presupuesto)',
            'pago compra fija consorcio (pagos)' => 'Pago de compra fija de consorcio (Pagos)',
            'compras fijas consorcios' => 'Compras fijas de consorcios',
            'mi perfil' => 'Mi perfil',
        ];

        $lookup = mb_strtolower($base, 'UTF-8');
        $formatted = $aliases[$lookup] ?? $base;

        if ($formatted === erp_brand_suffix()) {
            return $formatted;
        }

        return $formatted . ' | ' . erp_brand_suffix();
    }
}

if (!function_exists('erp_transform_head_markup')) {
    function erp_transform_head_markup(string $buffer): string
    {
        if ($buffer === '' || stripos($buffer, '<head') === false) {
            return $buffer;
        }

        $canonical = erp_canonical_url();
        if ($canonical !== null && !preg_match('/<link[^>]+rel=["\']canonical["\']/i', $buffer)) {
            $canonicalTag = "\n    <link rel=\"canonical\" href=\"" . htmlspecialchars($canonical, ENT_QUOTES, 'UTF-8') . "\">\n";
            $updated = preg_replace('/(<head\b[^>]*>)/i', '$1' . $canonicalTag, $buffer, 1);
            $buffer = is_string($updated) ? $updated : $buffer;
        }

        $buffer = preg_replace_callback('/<title>(.*?)<\/title>/is', static function (array $matches): string {
            return '<title>' . htmlspecialchars(erp_normalize_title_text($matches[1]), ENT_QUOTES, 'UTF-8') . '</title>';
        }, $buffer, 1) ?? $buffer;

        $faviconTag = "\n    " . erp_favicon_tag() . "\n";
        if (preg_match('/<link\b[^>]*rel=["\'](?:shortcut\s+)?icon["\'][^>]*>/i', $buffer)) {
            $updated = preg_replace('/\s*<link\b[^>]*rel=["\'](?:shortcut\s+)?icon["\'][^>]*>\s*/i', $faviconTag, $buffer, 1);
            $buffer = is_string($updated) ? $updated : $buffer;
        } else {
            $updated = preg_replace('/(<title>.*?<\/title>)/is', '$1' . $faviconTag, $buffer, 1);
            $buffer = is_string($updated) ? $updated : $buffer;
        }

        return $buffer;
    }
}

if (!function_exists('erp_request_scheme')) {
    function erp_request_scheme(): string
    {
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? '') === '443')
            || (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');

        return $isHttps ? 'https' : 'http';
    }
}

if (!function_exists('erp_request_host')) {
    function erp_request_host(): ?string
    {
        $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
        if ($host === '') {
            return null;
        }

        $sanitized = preg_replace('/[^a-zA-Z0-9\.\-\:\[\]]/', '', $host);

        return $sanitized !== '' ? $sanitized : null;
    }
}

if (!function_exists('erp_canonical_query_params')) {
    function erp_canonical_query_params(): array
    {
        $params = $_GET;

        foreach (array_keys($params) as $key) {
            if (in_array($key, ['timeout', 'fbclid', 'gclid'], true) || str_starts_with($key, 'utm_')) {
                unset($params[$key]);
            }
        }

        return $params;
    }
}

if (!function_exists('erp_canonical_url')) {
    function erp_canonical_url(): ?string
    {
        $host = erp_request_host();
        $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '');

        if ($host === null || $requestUri === '') {
            return null;
        }

        $path = (string) parse_url($requestUri, PHP_URL_PATH);
        if ($path === '') {
            $path = '/';
        }

        $canonical = erp_request_scheme() . '://' . $host . $path;
        $query = http_build_query(erp_canonical_query_params());

        if ($query !== '') {
            $canonical .= '?' . $query;
        }

        return $canonical;
    }
}

if (!function_exists('erp_send_canonical_header')) {
    function erp_send_canonical_header(): void
    {
        if (headers_sent()) {
            return;
        }

        $canonical = erp_canonical_url();
        if ($canonical === null) {
            return;
        }

        header('Link: <' . $canonical . '>; rel="canonical"', true);
    }
}

if (!function_exists('erp_start_canonical_buffer')) {
    function erp_start_canonical_buffer(): void
    {
        static $started = false;

        if ($started || !erp_request_looks_like_html()) {
            return;
        }

        $started = true;

        ob_start(static function (string $buffer): string {
            return erp_transform_head_markup($buffer);
        });
    }
}

if (!function_exists('erp_bootstrap_request')) {
    function erp_bootstrap_request(): void
    {
        static $bootstrapped = false;

        if ($bootstrapped) {
            return;
        }

        $bootstrapped = true;

        if (!erp_request_looks_like_html()) {
            return;
        }

        erp_send_canonical_header();
        erp_start_canonical_buffer();
    }
}

if (!function_exists('erp_redirect')) {
    function erp_redirect(string $path): never
    {
        $target = erp_app_url($path);

        if (!headers_sent()) {
            header('Location: ' . $target);
            exit();
        }

        echo '<script>window.location.href=' . json_encode($target, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) . ';</script>';
        exit();
    }
}

if (!function_exists('erp_send_private_page_headers')) {
    function erp_send_private_page_headers(): void
    {
        erp_send_canonical_header();

        if (headers_sent()) {
            return;
        }

        header('X-Robots-Tag: noindex, nofollow, noarchive', true);
        header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('Referrer-Policy: strict-origin-when-cross-origin');
    }
}

erp_bootstrap_request();
