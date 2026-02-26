<?php
if (!function_exists('vilcon_fix_mojibake_text')) {
    function vilcon_fix_mojibake_text(string $text): string
    {
        static $map = [
            'ÃƒÆ’Ã‚Â' => 'Ã',
            'ÃƒÂ' => 'Ã',
            'Ã¡' => 'á', 'Ã ' => 'à', 'Ã¢' => 'â', 'Ã£' => 'ã', 'Ã¤' => 'ä',
            'Ã©' => 'é', 'Ã¨' => 'è', 'Ãª' => 'ê', 'Ã«' => 'ë',
            'Ã­' => 'í', 'Ã¬' => 'ì', 'Ã®' => 'î', 'Ã¯' => 'ï',
            'Ã³' => 'ó', 'Ã²' => 'ò', 'Ã´' => 'ô', 'Ãµ' => 'õ', 'Ã¶' => 'ö',
            'Ãº' => 'ú', 'Ã¹' => 'ù', 'Ã»' => 'û', 'Ã¼' => 'ü',
            'Ã§' => 'ç',
            'Ã' => 'Á', 'Ã€' => 'À', 'Ã‚' => 'Â', 'Ãƒ' => 'Ã', 'Ã„' => 'Ä',
            'Ã‰' => 'É', 'Ãˆ' => 'È', 'ÃŠ' => 'Ê', 'Ã‹' => 'Ë',
            'Ã' => 'Í', 'ÃŒ' => 'Ì', 'ÃŽ' => 'Î', 'Ã' => 'Ï',
            'Ã“' => 'Ó', 'Ã’' => 'Ò', 'Ã”' => 'Ô', 'Ã•' => 'Õ', 'Ã–' => 'Ö',
            'Ãš' => 'Ú', 'Ã™' => 'Ù', 'Ã›' => 'Û', 'Ãœ' => 'Ü',
            'Ã‡' => 'Ç',
            'Âº' => 'º', 'Âª' => 'ª', 'Â°' => '°',
            'â€“' => '–', 'â€”' => '—', 'â€˜' => '‘', 'â€™' => '’',
            'â€œ' => '“', 'â€' => '”', 'â€¢' => '•', 'â€¦' => '…',
            'â‚¬' => '€',
            'Â' => '',
        ];

        $fixed = $text;
        for ($i = 0; $i < 4; $i++) {
            $next = strtr($fixed, $map);
            if ($next === $fixed) {
                break;
            }
            $fixed = $next;
        }
        return $fixed;
    }
}

if (!function_exists('vilcon_bootstrap_mojibake_fix')) {
    function vilcon_bootstrap_mojibake_fix(): void
    {
        static $started = false;
        if ($started) {
            return;
        }

        $started = true;
        ob_start(function (string $buffer): string {
            $contentType = '';
            foreach (headers_list() as $headerLine) {
                if (stripos($headerLine, 'Content-Type:') === 0) {
                    $contentType = strtolower($headerLine);
                    break;
                }
            }

            if ($contentType !== '' && strpos($contentType, 'text/html') === false) {
                return $buffer;
            }

            $trimmed = ltrim($buffer);
            if (strncmp($trimmed, '%PDF-', 5) === 0) {
                return $buffer;
            }
            if ($contentType === '' && $trimmed !== '' && ($trimmed[0] === '{' || $trimmed[0] === '[')) {
                return $buffer;
            }

            return vilcon_fix_mojibake_text($buffer);
        });
    }
}
