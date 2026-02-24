<?php

if (!function_exists('normalizarPerfilAcesso')) {
    function normalizarPerfilAcesso(string $perfil): string
    {
        $valor = trim(mb_strtolower($perfil, 'UTF-8'));
        $valor = str_replace(
            ['á', 'à', 'â', 'ã', 'é', 'ê', 'í', 'ó', 'ô', 'õ', 'ú', 'ç'],
            ['a', 'a', 'a', 'a', 'e', 'e', 'i', 'o', 'o', 'o', 'u', 'c'],
            $valor
        );
        $valor = preg_replace('/\s+/', ' ', $valor ?? '') ?? '';
        return trim($valor);
    }
}

if (!function_exists('modulosPorPerfil')) {
    function modulosPorPerfil(string $perfil): array
    {
        $p = normalizarPerfilAcesso($perfil);

        $admins = ['admin', 'administrador', 'superadmin', 'diretor', 'diretor geral', 'diretor-geral'];
        if (in_array($p, $admins, true)) {
            return [
                'dashboard', 'documental', 'oficina', 'transporte', 'rh',
                'seguranca', 'logistica', 'aprovacoes', 'relatorios', 'utilizadores', 'armazem',
            ];
        }

        $map = [
            'logistica' => ['logistica'],
            'transporte' => ['transporte'],
            'documental' => ['documental'],
            'oficina' => ['oficina'],
            'rh' => ['rh'],
            'aluguer equipamentos' => ['transporte'],
            'aluguer de equipamentos' => ['transporte'],
            'gestao de frotas' => ['transporte'],
            'frentista' => ['transporte'],
            'seguranca' => ['seguranca'],
            'checklist hse' => ['oficina'],
        ];

        return $map[$p] ?? [];
    }
}

if (!function_exists('usuarioPodeAcederModulo')) {
    function usuarioPodeAcederModulo(string $perfil, string $modulo): bool
    {
        $moduloNorm = normalizarPerfilAcesso($modulo);
        return in_array($moduloNorm, modulosPorPerfil($perfil), true);
    }
}

if (!function_exists('moduloAtualPorScript')) {
    function moduloAtualPorScript(string $scriptName): ?string
    {
        if (preg_match('#/modules/([^/]+)/#', $scriptName, $m) === 1) {
            return strtolower(trim((string)$m[1]));
        }
        return null;
    }
}

if (!function_exists('garantirAcessoModuloAtual')) {
    function garantirAcessoModuloAtual(string $scriptName, string $perfil): void
    {
        $modulo = moduloAtualPorScript($scriptName);
        if ($modulo === null) {
            return;
        }

        if (!usuarioPodeAcederModulo($perfil, $modulo)) {
            http_response_code(403);
            exit('Acesso negado: sem permissao para este modulo.');
        }
    }
}
