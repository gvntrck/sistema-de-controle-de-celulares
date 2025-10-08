<?php
/**
 * Arquivo único: /celulares-admin.php
 * Localização: /sistemas/celulares/sistema.php
 * MVP: lista celulares + metadados + dados do colaborador.
 * Version: 2.2.1
 */

declare(strict_types=1);

// --- Bootstrap WordPress ---
require_once __DIR__ . '/../../wp-load.php';

/** @var wpdb $wpdb */
global $wpdb;

// --- Verificar autenticação ---
if (!is_user_logged_in()) {
    // Processar login se formulário foi enviado
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_submit'])) {
        $username = sanitize_user($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        $user = wp_signon([
            'user_login' => $username,
            'user_password' => $password,
            'remember' => true
        ], false);
        
        if (is_wp_error($user)) {
            $login_error = 'Usuário ou senha incorretos.';
        } else {
            wp_set_current_user($user->ID);
            wp_set_auth_cookie($user->ID, true);
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
    }
    
    // Exibir formulário de login
    ?>
    <!doctype html>
    <html lang="pt-br">
    <head>
        <meta charset="utf-8">
        <title>Login - Controle de Celulares</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            body {
                background: #f5f5f5;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }
            .login-card {
                background: white;
                border: 1px solid #ddd;
                border-radius: 5px;
                padding: 30px;
                max-width: 400px;
                width: 100%;
            }
            .login-header {
                text-align: center;
                margin-bottom: 25px;
            }
            .login-header h1 {
                color: #333;
                font-size: 22px;
                font-weight: 500;
                margin-bottom: 5px;
            }
        </style>
    </head>
    <body>
        <div class="login-card">
            <div class="login-header">
                <h1>Controle de Celulares</h1>
            </div>
            
            <?php if (isset($login_error)): ?>
                <div class="alert alert-danger" role="alert">
                    <?php echo esc_html($login_error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="mb-3">
                    <label for="username" class="form-label">Usuário</label>
                    <input type="text" class="form-control" id="username" name="username" required autofocus>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Senha</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                <button type="submit" name="login_submit" class="btn btn-primary w-100">
                    Entrar
                </button>
            </form>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>
    <?php
    exit;
}

// Usuário autenticado - continuar com o sistema
$current_user = wp_get_current_user();

// --- Configurações básicas ---
$prefix = $wpdb->prefix;
$tables = (object) [
    'celulares'          => $prefix . 'celulares',
    'celulares_meta'     => $prefix . 'celulares_meta',
    'colaboradores'      => $prefix . 'colaboradores',
    'colaboradores_meta' => $prefix . 'colaboradores_meta',
    'transferencias'     => $prefix . 'celulares_transferencias',
    'auditoria'          => $prefix . 'celulares_auditoria',
];

// --- Helpers mínimos ---
function esc($v): string { return esc_html((string) $v); }
function collate(): string {
    global $wpdb;
    return $wpdb->get_charset_collate();
}

/**
 * Cria tabelas se não existirem.
 * Uso do modelo key/value para expansão simples.
 */
function ensure_schema(): void {
    global $wpdb, $tables;

    $sql = [];

    $sql[] = "CREATE TABLE IF NOT EXISTS {$tables->colaboradores} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        nome VARCHAR(191) NOT NULL,
        sobrenome VARCHAR(191) NOT NULL,
        matricula VARCHAR(191) NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_matricula (matricula)
    ) " . collate() . ";";

    $sql[] = "CREATE TABLE IF NOT EXISTS {$tables->colaboradores_meta} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        colaborador_id BIGINT UNSIGNED NOT NULL,
        meta_key VARCHAR(191) NOT NULL,
        meta_value LONGTEXT NULL,
        PRIMARY KEY (id),
        KEY idx_colab (colaborador_id),
        KEY idx_key (meta_key),
        CONSTRAINT fk_colab_meta FOREIGN KEY (colaborador_id)
            REFERENCES {$tables->colaboradores}(id) ON DELETE CASCADE
    ) " . collate() . ";";

    $sql[] = "CREATE TABLE IF NOT EXISTS {$tables->celulares} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        marca VARCHAR(191) NOT NULL,
        modelo VARCHAR(191) NOT NULL,
        colaborador BIGINT UNSIGNED NULL, -- FK para colaboradores.id
        status VARCHAR(50) NOT NULL DEFAULT 'disponivel', -- ex: disponivel, emprestado, manutencao, inativo, defeito, perdido, roubado, descartado
        PRIMARY KEY (id),
        KEY idx_colaborador (colaborador),
        CONSTRAINT fk_cel_colab FOREIGN KEY (colaborador)
            REFERENCES {$tables->colaboradores}(id) ON DELETE SET NULL
    ) " . collate() . ";";

    $sql[] = "CREATE TABLE IF NOT EXISTS {$tables->celulares_meta} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        celular_id BIGINT UNSIGNED NOT NULL,
        meta_key VARCHAR(191) NOT NULL,
        meta_value LONGTEXT NULL,
        PRIMARY KEY (id),
        KEY idx_cel (celular_id),
        KEY idx_key (meta_key),
        CONSTRAINT fk_cel_meta FOREIGN KEY (celular_id)
            REFERENCES {$tables->celulares}(id) ON DELETE CASCADE
    ) " . collate() . ";";

    $sql[] = "CREATE TABLE IF NOT EXISTS {$tables->transferencias} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        celular_id BIGINT UNSIGNED NOT NULL,
        colaborador_anterior BIGINT UNSIGNED NULL,
        colaborador_novo BIGINT UNSIGNED NULL,
        data_transferencia DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        observacao TEXT NULL,
        pdf_recebimento VARCHAR(255) NULL,
        pdf_devolucao VARCHAR(255) NULL,
        PRIMARY KEY (id),
        KEY idx_celular (celular_id),
        KEY idx_data (data_transferencia),
        CONSTRAINT fk_transf_cel FOREIGN KEY (celular_id)
            REFERENCES {$tables->celulares}(id) ON DELETE CASCADE,
        CONSTRAINT fk_transf_colab_ant FOREIGN KEY (colaborador_anterior)
            REFERENCES {$tables->colaboradores}(id) ON DELETE SET NULL,
        CONSTRAINT fk_transf_colab_novo FOREIGN KEY (colaborador_novo)
            REFERENCES {$tables->colaboradores}(id) ON DELETE SET NULL
    ) " . collate() . ";";

    $sql[] = "CREATE TABLE IF NOT EXISTS {$tables->auditoria} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        usuario_id BIGINT UNSIGNED NULL,
        usuario_nome VARCHAR(191) NOT NULL,
        acao VARCHAR(50) NOT NULL,
        tabela VARCHAR(50) NOT NULL,
        registro_id BIGINT UNSIGNED NULL,
        dados_anteriores LONGTEXT NULL,
        dados_novos LONGTEXT NULL,
        ip_address VARCHAR(45) NULL,
        user_agent VARCHAR(255) NULL,
        data_hora DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_usuario (usuario_id),
        KEY idx_acao (acao),
        KEY idx_tabela (tabela),
        KEY idx_registro (registro_id),
        KEY idx_data (data_hora)
    ) " . collate() . ";";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    foreach ($sql as $q) {
        $wpdb->query($q); // idempotente com IF NOT EXISTS
    }

    // Garante chaves importantes de meta iniciais no comentário abaixo.
    // celulares_meta padrão: imei, serial number
    // colaboradores_meta padrão: setor, local
}

/**
 * Registra ação de auditoria no sistema
 */
function registrar_auditoria(string $acao, string $tabela, ?int $registro_id = null, ?array $dados_anteriores = null, ?array $dados_novos = null): void {
    global $wpdb, $tables, $current_user;
    
    $usuario_id = $current_user->ID ?? null;
    $usuario_nome = $current_user->display_name ?? 'Sistema';
    
    // Capturar IP do usuário
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'];
    }
    
    // Capturar User Agent
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    if ($user_agent && strlen($user_agent) > 255) {
        $user_agent = substr($user_agent, 0, 255);
    }
    
    // Preparar dados para JSON
    $dados_ant_json = $dados_anteriores ? wp_json_encode($dados_anteriores, JSON_UNESCAPED_UNICODE) : null;
    $dados_nov_json = $dados_novos ? wp_json_encode($dados_novos, JSON_UNESCAPED_UNICODE) : null;
    
    // Inserir log de auditoria
    $wpdb->insert($tables->auditoria, [
        'usuario_id' => $usuario_id,
        'usuario_nome' => $usuario_nome,
        'acao' => $acao,
        'tabela' => $tabela,
        'registro_id' => $registro_id,
        'dados_anteriores' => $dados_ant_json,
        'dados_novos' => $dados_nov_json,
        'ip_address' => $ip_address,
        'user_agent' => $user_agent,
        'data_hora' => current_time('mysql')
    ]);
}

/**
 * Busca estatísticas do sistema para o dashboard
 */
function fetch_dashboard_stats(): array {
    global $wpdb, $tables;
    
    // Total de celulares por status
    $stats_status = $wpdb->get_results("
        SELECT status, COUNT(*) as total
        FROM {$tables->celulares}
        GROUP BY status
    ", ARRAY_A);
    
    $stats = [
        'disponivel' => 0,
        'emprestado' => 0,
        'manutencao' => 0,
        'defeito' => 0,
        'inativo' => 0,
        'perdido' => 0,
        'roubado' => 0,
        'descartado' => 0,
        'total' => 0
    ];
    
    foreach ($stats_status as $row) {
        $status = $row['status'];
        $total = (int) $row['total'];
        if (isset($stats[$status])) {
            $stats[$status] = $total;
        }
        $stats['total'] += $total;
    }
    
    // Total de colaboradores com celular
    $stats['colaboradores_com_celular'] = (int) $wpdb->get_var("
        SELECT COUNT(DISTINCT colaborador)
        FROM {$tables->celulares}
        WHERE colaborador IS NOT NULL
    ");
    
    // Total de colaboradores cadastrados
    $stats['total_colaboradores'] = (int) $wpdb->get_var("
        SELECT COUNT(*) FROM {$tables->colaboradores}
    ");
    
    // Total de transferências no último mês
    $stats['transferencias_mes'] = (int) $wpdb->get_var("
        SELECT COUNT(*)
        FROM {$tables->transferencias}
        WHERE data_transferencia >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    
    // Celulares em manutenção há mais de 30 dias
    $stats['manutencao_longa'] = (int) $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(DISTINCT c.id)
        FROM {$tables->celulares} c
        LEFT JOIN {$tables->transferencias} t ON t.celular_id = c.id
        WHERE c.status = %s
        AND (t.data_transferencia IS NULL OR t.data_transferencia < DATE_SUB(NOW(), INTERVAL 30 DAY))
    ", 'manutencao'));
    
    // Distribuição por propriedade
    $stats_prop = $wpdb->get_results("
        SELECT cm.meta_value as propriedade, COUNT(*) as total
        FROM {$tables->celulares} c
        LEFT JOIN {$tables->celulares_meta} cm ON cm.celular_id = c.id AND cm.meta_key = 'propriedade'
        WHERE cm.meta_value IS NOT NULL
        GROUP BY cm.meta_value
    ", ARRAY_A);
    
    $stats['metalife'] = 0;
    $stats['selbetti'] = 0;
    
    foreach ($stats_prop as $row) {
        if ($row['propriedade'] === 'Metalife') {
            $stats['metalife'] = (int) $row['total'];
        } elseif ($row['propriedade'] === 'Selbetti') {
            $stats['selbetti'] = (int) $row['total'];
        }
    }
    
    return $stats;
}

/**
 * Consulta principal: celulares + metadados + colaborador + metadados
 */
function fetch_rows(): array {
    global $wpdb, $tables;

    $q = "
        SELECT
            c.id,
            c.marca,
            c.modelo,
            c.status,
            c.colaborador AS colaborador_id,
            col.nome,
            col.sobrenome,
            col.matricula,
            imei.meta_value   AS imei,
            serial.meta_value AS serial_number,
            setor.meta_value  AS setor,
            local.meta_value  AS local_trabalho
        FROM {$tables->celulares} c
        LEFT JOIN {$tables->colaboradores} col
            ON col.id = c.colaborador
        LEFT JOIN {$tables->celulares_meta} imei
            ON imei.celular_id = c.id AND imei.meta_key = 'imei'
        LEFT JOIN {$tables->celulares_meta} serial
            ON serial.celular_id = c.id AND serial.meta_key = 'serial number'
        LEFT JOIN {$tables->colaboradores_meta} setor
            ON setor.colaborador_id = col.id AND setor.meta_key = 'setor'
        LEFT JOIN {$tables->colaboradores_meta} local
            ON local.colaborador_id = col.id AND local.meta_key = 'local'
        ORDER BY c.id DESC
        LIMIT 1000
    ";
    /** @var array<int,array<string,mixed>> */
    $rows = $wpdb->get_results($q, ARRAY_A) ?: [];
    return $rows;
}

// --- Helper para cadastrar colaborador ---
function criar_colaborador(array $input): ?int {
    global $wpdb, $tables;
    
    if (empty($input['colaborador_nome']) || empty($input['colaborador_sobrenome']) || empty($input['colaborador_matricula'])) {
        return null;
    }
    
    $dados_colaborador = [
        'nome' => sanitize_text_field($input['colaborador_nome']),
        'sobrenome' => sanitize_text_field($input['colaborador_sobrenome']),
        'matricula' => sanitize_text_field($input['colaborador_matricula'])
    ];
    
    $wpdb->insert($tables->colaboradores, $dados_colaborador);
    
    $colaborador_id = (int) $wpdb->insert_id;
    
    // Salvar metas do colaborador
    if (!empty($input['colaborador_setor'])) {
        $wpdb->insert($tables->colaboradores_meta, [
            'colaborador_id' => $colaborador_id,
            'meta_key' => 'setor',
            'meta_value' => sanitize_text_field($input['colaborador_setor'])
        ]);
        $dados_colaborador['setor'] = sanitize_text_field($input['colaborador_setor']);
    }
    
    if (!empty($input['colaborador_local'])) {
        $wpdb->insert($tables->colaboradores_meta, [
            'colaborador_id' => $colaborador_id,
            'meta_key' => 'local',
            'meta_value' => sanitize_text_field($input['colaborador_local'])
        ]);
        $dados_colaborador['local'] = sanitize_text_field($input['colaborador_local']);
    }
    
    // Registrar auditoria
    registrar_auditoria('criar', 'colaboradores', $colaborador_id, null, $dados_colaborador);
    
    return $colaborador_id;
}

// --- Helper para salvar metas do celular ---
function salvar_metas_celular(int $celular_id, array $input): void {
    global $wpdb, $tables;
    
    $metas = [
        'imei' => $input['imei'] ?? '',
        'serial number' => $input['serial_number'] ?? '',
        'data_aquisicao' => $input['data_aquisicao'] ?? '',
        'data_entrega' => $input['data_entrega'] ?? '',
        'propriedade' => $input['propriedade'] ?? '',
        'selb' => $input['selb'] ?? '',
        'observacao' => $input['observacao'] ?? ''
    ];
    
    foreach ($metas as $key => $value) {
        if (!empty($value)) {
            $wpdb->insert($tables->celulares_meta, [
                'celular_id' => $celular_id,
                'meta_key' => $key,
                'meta_value' => $key === 'observacao' ? sanitize_textarea_field($value) : sanitize_text_field($value)
            ]);
        }
    }
}

// --- Helper para atualizar ou inserir metas do celular ---
function upsert_metas_celular(int $celular_id, array $input): void {
    global $wpdb, $tables;
    
    $metas = [
        'imei' => $input['imei'] ?? '',
        'serial number' => $input['serial_number'] ?? '',
        'data_aquisicao' => $input['data_aquisicao'] ?? '',
        'data_entrega' => $input['data_entrega'] ?? '',
        'propriedade' => $input['propriedade'] ?? '',
        'selb' => $input['selb'] ?? '',
        'observacao' => $input['observacao'] ?? ''
    ];
    
    foreach ($metas as $key => $value) {
        if (!empty($value)) {
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$tables->celulares_meta} WHERE celular_id = %d AND meta_key = %s",
                $celular_id,
                $key
            ));
            
            $sanitized_value = $key === 'observacao' ? sanitize_textarea_field($value) : sanitize_text_field($value);
            
            if ($exists) {
                $wpdb->update(
                    $tables->celulares_meta,
                    ['meta_value' => $sanitized_value],
                    ['celular_id' => $celular_id, 'meta_key' => $key]
                );
            } else {
                $wpdb->insert($tables->celulares_meta, [
                    'celular_id' => $celular_id,
                    'meta_key' => $key,
                    'meta_value' => $sanitized_value
                ]);
            }
        }
    }
}

/**
 * Gera PDF de ficha de recebimento de celular
 */
function gerar_pdf_recebimento(int $celular_id, int $colaborador_id): ?string {
    global $wpdb, $tables;
    
    // Buscar dados do celular
    $celular = $wpdb->get_row($wpdb->prepare("
        SELECT c.*, 
               imei.meta_value AS imei,
               serial.meta_value AS serial_number,
               prop.meta_value AS propriedade
        FROM {$tables->celulares} c
        LEFT JOIN {$tables->celulares_meta} imei ON imei.celular_id = c.id AND imei.meta_key = 'imei'
        LEFT JOIN {$tables->celulares_meta} serial ON serial.celular_id = c.id AND serial.meta_key = 'serial number'
        LEFT JOIN {$tables->celulares_meta} prop ON prop.celular_id = c.id AND prop.meta_key = 'propriedade'
        WHERE c.id = %d
    ", $celular_id), ARRAY_A);
    
    if (!$celular) {
        return null;
    }
    
    // Buscar dados do colaborador
    $colaborador = $wpdb->get_row($wpdb->prepare("
        SELECT col.*,
               setor.meta_value AS setor,
               local.meta_value AS local
        FROM {$tables->colaboradores} col
        LEFT JOIN {$tables->colaboradores_meta} setor ON setor.colaborador_id = col.id AND setor.meta_key = 'setor'
        LEFT JOIN {$tables->colaboradores_meta} local ON local.colaborador_id = col.id AND local.meta_key = 'local'
        WHERE col.id = %d
    ", $colaborador_id), ARRAY_A);
    
    if (!$colaborador) {
        return null;
    }
    
    // Carregar TCPDF
    require_once ABSPATH . 'wp-includes/class-phpass.php';
    require_once ABSPATH . 'wp-includes/class-phpmailer.php';
    
    // Verificar se TCPDF está disponível no WordPress
    if (!class_exists('TCPDF')) {
        // Usar biblioteca alternativa ou HTML simples
        return gerar_pdf_html_recebimento($celular, $colaborador);
    }
    
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('Sistema de Controle de Celulares');
    $pdf->SetAuthor('Metalife');
    $pdf->SetTitle('Ficha de Recebimento de Celular');
    
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(true, 15);
    
    $pdf->AddPage();
    $pdf->SetFont('helvetica', '', 10);
    
    $data_atual = date('d/m/Y H:i');
    
    $html = "
    <h1 style=\"text-align:center; color:#333;\">FICHA DE RECEBIMENTO DE CELULAR</h1>
    <p style=\"text-align:center; font-size:10px; color:#666;\">Data: {$data_atual}</p>
    <hr>
    
    <h3 style=\"color:#0066cc;\">Dados do Aparelho</h3>
    <table cellpadding=\"5\" style=\"width:100%; border:1px solid #ccc;\">
        <tr>
            <td style=\"width:30%; background-color:#f5f5f5;\"><strong>Marca:</strong></td>
            <td style=\"width:70%;\">{$celular['marca']}</td>
        </tr>
        <tr>
            <td style=\"background-color:#f5f5f5;\"><strong>Modelo:</strong></td>
            <td>{$celular['modelo']}</td>
        </tr>
        <tr>
            <td style=\"background-color:#f5f5f5;\"><strong>IMEI:</strong></td>
            <td>{$celular['imei']}</td>
        </tr>
        <tr>
            <td style=\"background-color:#f5f5f5;\"><strong>Serial Number:</strong></td>
            <td>{$celular['serial_number']}</td>
        </tr>
        <tr>
            <td style=\"background-color:#f5f5f5;\"><strong>Propriedade:</strong></td>
            <td>{$celular['propriedade']}</td>
        </tr>
    </table>
    
    <br><br>
    <h3 style=\"color:#0066cc;\">Dados do Colaborador</h3>
    <table cellpadding=\"5\" style=\"width:100%; border:1px solid #ccc;\">
        <tr>
            <td style=\"width:30%; background-color:#f5f5f5;\"><strong>Nome:</strong></td>
            <td style=\"width:70%;\">{$colaborador['nome']} {$colaborador['sobrenome']}</td>
        </tr>
        <tr>
            <td style=\"background-color:#f5f5f5;\"><strong>Matrícula:</strong></td>
            <td>{$colaborador['matricula']}</td>
        </tr>
        <tr>
            <td style=\"background-color:#f5f5f5;\"><strong>Setor:</strong></td>
            <td>{$colaborador['setor']}</td>
        </tr>
        <tr>
            <td style=\"background-color:#f5f5f5;\"><strong>Local:</strong></td>
            <td>{$colaborador['local']}</td>
        </tr>
    </table>
    
    <br><br>
    <h3 style=\"color:#0066cc;\">Termo de Responsabilidade</h3>
    <p style=\"text-align:justify; line-height:1.6;\">
        Declaro que recebi o aparelho celular descrito acima em perfeitas condições de uso e funcionamento.
        Comprometo-me a utilizar o equipamento exclusivamente para fins profissionais e a zelar pela sua
        conservação e segurança. Estou ciente de que sou responsável pelo aparelho e que, em caso de perda,
        roubo ou dano, devo comunicar imediatamente ao setor responsável.
    </p>
    
    <br><br><br>
    <table style=\"width:100%;\">
        <tr>
            <td style=\"width:50%; text-align:center; border-top:1px solid #000;\">
                <br>Assinatura do Colaborador
            </td>
            <td style=\"width:50%; text-align:center; border-top:1px solid #000;\">
                <br>Assinatura do Responsável
            </td>
        </tr>
    </table>
    ";
    
    $pdf->writeHTML($html, true, false, true, false, '');
    
    // Salvar PDF
    $upload_dir = wp_upload_dir();
    $pdf_dir = $upload_dir['basedir'] . '/celulares-pdfs/';
    
    if (!file_exists($pdf_dir)) {
        wp_mkdir_p($pdf_dir);
    }
    
    $filename = 'recebimento_' . $celular_id . '_' . $colaborador_id . '_' . time() . '.pdf';
    $filepath = $pdf_dir . $filename;
    
    $pdf->Output($filepath, 'F');
    
    return $upload_dir['baseurl'] . '/celulares-pdfs/' . $filename;
}

/**
 * Gera PDF de ficha de devolução de celular (versão HTML simples)
 */
function gerar_pdf_html_recebimento(array $celular, array $colaborador): string {
    $upload_dir = wp_upload_dir();
    $pdf_dir = $upload_dir['basedir'] . '/celulares-pdfs/';
    
    if (!file_exists($pdf_dir)) {
        wp_mkdir_p($pdf_dir);
    }
    
    $data_atual = date('d/m/Y H:i');
    
    $html = "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>Ficha de Recebimento</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css' rel='stylesheet'>
    <style>
        body { padding: 40px; font-family: Arial, sans-serif; }
        .header { text-align: center; margin-bottom: 30px; }
        .section { margin-bottom: 30px; }
        .signature-box { border-top: 1px solid #000; margin-top: 100px; padding-top: 10px; text-align: center; }
        @media print {
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class='no-print text-end mb-3'>
        <button class='btn btn-primary' onclick='window.print()'>Imprimir</button>
    </div>
    
    <div class='header'>
        <h1>FICHA DE RECEBIMENTO DE CELULAR</h1>
        <p class='text-muted'>Data: {$data_atual}</p>
    </div>
    
    <div class='section'>
        <h3 class='text-primary'>Dados do Aparelho</h3>
        <table class='table table-bordered'>
            <tr><td class='bg-light' width='30%'><strong>Marca:</strong></td><td>{$celular['marca']}</td></tr>
            <tr><td class='bg-light'><strong>Modelo:</strong></td><td>{$celular['modelo']}</td></tr>
            <tr><td class='bg-light'><strong>IMEI:</strong></td><td>{$celular['imei']}</td></tr>
            <tr><td class='bg-light'><strong>Serial Number:</strong></td><td>{$celular['serial_number']}</td></tr>
            <tr><td class='bg-light'><strong>Propriedade:</strong></td><td>{$celular['propriedade']}</td></tr>
        </table>
    </div>
    
    <div class='section'>
        <h3 class='text-primary'>Dados do Colaborador</h3>
        <table class='table table-bordered'>
            <tr><td class='bg-light' width='30%'><strong>Nome:</strong></td><td>{$colaborador['nome']} {$colaborador['sobrenome']}</td></tr>
            <tr><td class='bg-light'><strong>Matrícula:</strong></td><td>{$colaborador['matricula']}</td></tr>
            <tr><td class='bg-light'><strong>Setor:</strong></td><td>{$colaborador['setor']}</td></tr>
            <tr><td class='bg-light'><strong>Local:</strong></td><td>{$colaborador['local']}</td></tr>
        </table>
    </div>
    
    <div class='section'>
        <h3 class='text-primary'>Termo de Responsabilidade</h3>
        <p style='text-align:justify; line-height:1.8;'>
            Declaro que recebi o aparelho celular descrito acima em perfeitas condições de uso e funcionamento.
            Comprometo-me a utilizar o equipamento exclusivamente para fins profissionais e a zelar pela sua
            conservação e segurança. Estou ciente de que sou responsável pelo aparelho e que, em caso de perda,
            roubo ou dano, devo comunicar imediatamente ao setor responsável.
        </p>
    </div>
    
    <div class='row mt-5'>
        <div class='col-6'>
            <div class='signature-box'>Assinatura do Colaborador</div>
        </div>
        <div class='col-6'>
            <div class='signature-box'>Assinatura do Responsável</div>
        </div>
    </div>
</body>
</html>";
    
    $filename = 'recebimento_' . $celular['id'] . '_' . $colaborador['id'] . '_' . time() . '.html';
    $filepath = $pdf_dir . $filename;
    
    file_put_contents($filepath, $html);
    
    return $upload_dir['baseurl'] . '/celulares-pdfs/' . $filename;
}

/**
 * Gera PDF de ficha de devolução de celular
 */
function gerar_pdf_devolucao(int $celular_id, int $colaborador_id): ?string {
    global $wpdb, $tables;
    
    // Buscar dados do celular
    $celular = $wpdb->get_row($wpdb->prepare("
        SELECT c.*, 
               imei.meta_value AS imei,
               serial.meta_value AS serial_number,
               prop.meta_value AS propriedade
        FROM {$tables->celulares} c
        LEFT JOIN {$tables->celulares_meta} imei ON imei.celular_id = c.id AND imei.meta_key = 'imei'
        LEFT JOIN {$tables->celulares_meta} serial ON serial.celular_id = c.id AND serial.meta_key = 'serial number'
        LEFT JOIN {$tables->celulares_meta} prop ON prop.celular_id = c.id AND prop.meta_key = 'propriedade'
        WHERE c.id = %d
    ", $celular_id), ARRAY_A);
    
    if (!$celular) {
        return null;
    }
    
    // Buscar dados do colaborador
    $colaborador = $wpdb->get_row($wpdb->prepare("
        SELECT col.*,
               setor.meta_value AS setor,
               local.meta_value AS local
        FROM {$tables->colaboradores} col
        LEFT JOIN {$tables->colaboradores_meta} setor ON setor.colaborador_id = col.id AND setor.meta_key = 'setor'
        LEFT JOIN {$tables->colaboradores_meta} local ON local.colaborador_id = col.id AND local.meta_key = 'local'
        WHERE col.id = %d
    ", $colaborador_id), ARRAY_A);
    
    if (!$colaborador) {
        return null;
    }
    
    $upload_dir = wp_upload_dir();
    $pdf_dir = $upload_dir['basedir'] . '/celulares-pdfs/';
    
    if (!file_exists($pdf_dir)) {
        wp_mkdir_p($pdf_dir);
    }
    
    $data_atual = date('d/m/Y H:i');
    
    $html = "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>Ficha de Devolução</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css' rel='stylesheet'>
    <style>
        body { padding: 40px; font-family: Arial, sans-serif; }
        .header { text-align: center; margin-bottom: 30px; }
        .section { margin-bottom: 30px; }
        .signature-box { border-top: 1px solid #000; margin-top: 100px; padding-top: 10px; text-align: center; }
        .checklist { list-style: none; padding-left: 0; }
        .checklist li { margin-bottom: 10px; }
        .checklist input[type='checkbox'] { margin-right: 10px; }
        @media print {
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class='no-print text-end mb-3'>
        <button class='btn btn-primary' onclick='window.print()'>Imprimir</button>
    </div>
    
    <div class='header'>
        <h1>FICHA DE DEVOLUÇÃO DE CELULAR</h1>
        <p class='text-muted'>Data: {$data_atual}</p>
    </div>
    
    <div class='section'>
        <h3 class='text-primary'>Dados do Aparelho</h3>
        <table class='table table-bordered'>
            <tr><td class='bg-light' width='30%'><strong>Marca:</strong></td><td>{$celular['marca']}</td></tr>
            <tr><td class='bg-light'><strong>Modelo:</strong></td><td>{$celular['modelo']}</td></tr>
            <tr><td class='bg-light'><strong>IMEI:</strong></td><td>{$celular['imei']}</td></tr>
            <tr><td class='bg-light'><strong>Serial Number:</strong></td><td>{$celular['serial_number']}</td></tr>
            <tr><td class='bg-light'><strong>Propriedade:</strong></td><td>{$celular['propriedade']}</td></tr>
        </table>
    </div>
    
    <div class='section'>
        <h3 class='text-primary'>Dados do Colaborador</h3>
        <table class='table table-bordered'>
            <tr><td class='bg-light' width='30%'><strong>Nome:</strong></td><td>{$colaborador['nome']} {$colaborador['sobrenome']}</td></tr>
            <tr><td class='bg-light'><strong>Matrícula:</strong></td><td>{$colaborador['matricula']}</td></tr>
            <tr><td class='bg-light'><strong>Setor:</strong></td><td>{$colaborador['setor']}</td></tr>
            <tr><td class='bg-light'><strong>Local:</strong></td><td>{$colaborador['local']}</td></tr>
        </table>
    </div>
    
    <div class='section'>
        <h3 class='text-primary'>Checklist de Devolução</h3>
        <ul class='checklist'>
            <li><input type='checkbox'> Aparelho em boas condições físicas (sem trincas, arranhões graves)</li>
            <li><input type='checkbox'> Tela funcionando perfeitamente</li>
            <li><input type='checkbox'> Bateria com boa autonomia</li>
            <li><input type='checkbox'> Carregador devolvido</li>
            <li><input type='checkbox'> Cabo USB devolvido</li>
            <li><input type='checkbox'> Capa/proteção devolvida (se aplicável)</li>
            <li><input type='checkbox'> Dados pessoais removidos (factory reset)</li>
        </ul>
    </div>
    
    <div class='section'>
        <h3 class='text-primary'>Observações</h3>
        <div style='border: 1px solid #ccc; min-height: 100px; padding: 10px;'>
            <p class='text-muted'>Descreva aqui qualquer observação sobre o estado do aparelho:</p>
        </div>
    </div>
    
    <div class='row mt-5'>
        <div class='col-6'>
            <div class='signature-box'>Assinatura do Colaborador</div>
        </div>
        <div class='col-6'>
            <div class='signature-box'>Assinatura do Responsável</div>
        </div>
    </div>
</body>
</html>";
    
    $filename = 'devolucao_' . $celular['id'] . '_' . $colaborador['id'] . '_' . time() . '.html';
    $filepath = $pdf_dir . $filename;
    
    file_put_contents($filepath, $html);
    
    return $upload_dir['baseurl'] . '/celulares-pdfs/' . $filename;
}

/**
 * Registra transferência de celular e gera PDFs
 */
function registrar_transferencia(int $celular_id, ?int $colaborador_anterior, ?int $colaborador_novo): ?int {
    global $wpdb, $tables;
    
    // Gerar PDFs
    $pdf_devolucao = null;
    $pdf_recebimento = null;
    
    if ($colaborador_anterior) {
        $pdf_devolucao = gerar_pdf_devolucao($celular_id, $colaborador_anterior);
    }
    
    if ($colaborador_novo) {
        // Buscar dados completos do celular com metadados
        $celular = $wpdb->get_row($wpdb->prepare("
            SELECT c.*, 
                   imei.meta_value AS imei,
                   serial.meta_value AS serial_number,
                   prop.meta_value AS propriedade
            FROM {$tables->celulares} c
            LEFT JOIN {$tables->celulares_meta} imei ON imei.celular_id = c.id AND imei.meta_key = 'imei'
            LEFT JOIN {$tables->celulares_meta} serial ON serial.celular_id = c.id AND serial.meta_key = 'serial number'
            LEFT JOIN {$tables->celulares_meta} prop ON prop.celular_id = c.id AND prop.meta_key = 'propriedade'
            WHERE c.id = %d
        ", $celular_id), ARRAY_A);
        
        // Buscar dados completos do colaborador com metadados
        $colaborador = $wpdb->get_row($wpdb->prepare("
            SELECT col.*,
                   setor.meta_value AS setor,
                   local.meta_value AS local
            FROM {$tables->colaboradores} col
            LEFT JOIN {$tables->colaboradores_meta} setor ON setor.colaborador_id = col.id AND setor.meta_key = 'setor'
            LEFT JOIN {$tables->colaboradores_meta} local ON local.colaborador_id = col.id AND local.meta_key = 'local'
            WHERE col.id = %d
        ", $colaborador_novo), ARRAY_A);
        
        if ($celular && $colaborador) {
            $pdf_recebimento = gerar_pdf_html_recebimento($celular, $colaborador);
        }
    }
    
    // Inserir registro de transferência
    $wpdb->insert($tables->transferencias, [
        'celular_id' => $celular_id,
        'colaborador_anterior' => $colaborador_anterior,
        'colaborador_novo' => $colaborador_novo,
        'data_transferencia' => current_time('mysql'),
        'pdf_recebimento' => $pdf_recebimento,
        'pdf_devolucao' => $pdf_devolucao
    ]);
    
    return (int) $wpdb->insert_id;
}

// --- Handlers AJAX ---
function handle_ajax(): void {
    global $wpdb, $tables;
    
    if (!isset($_GET['action'])) {
        return;
    }
    
    header('Content-Type: application/json');
    
    // Buscar colaboradores
    if ($_GET['action'] === 'buscar_colaboradores') {
        $termo = isset($_GET['termo']) ? sanitize_text_field($_GET['termo']) : '';
        
        $sql = $wpdb->prepare(
            "SELECT id, nome, sobrenome, matricula FROM {$tables->colaboradores} 
             WHERE nome LIKE %s OR sobrenome LIKE %s OR matricula LIKE %s 
             ORDER BY nome LIMIT 20",
            '%' . $wpdb->esc_like($termo) . '%',
            '%' . $wpdb->esc_like($termo) . '%',
            '%' . $wpdb->esc_like($termo) . '%'
        );
        
        $colaboradores = $wpdb->get_results($sql, ARRAY_A);
        echo json_encode(['success' => true, 'data' => $colaboradores]);
        exit;
    }
    
    // Salvar celular
    if ($_GET['action'] === 'salvar_celular' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Determinar colaborador (novo ou existente)
        $colaborador_id = !empty($input['novo_colaborador']) 
            ? criar_colaborador($input) 
            : (!empty($input['colaborador_id']) ? (int) $input['colaborador_id'] : null);
        
        // Preparar dados do celular
        $dados_celular = [
            'marca' => sanitize_text_field($input['marca']),
            'modelo' => sanitize_text_field($input['modelo']),
            'colaborador' => $colaborador_id,
            'status' => sanitize_text_field($input['status'])
        ];
        
        // Inserir celular
        $wpdb->insert($tables->celulares, $dados_celular);
        $celular_id = (int) $wpdb->insert_id;
        
        // Adicionar metas aos dados para auditoria
        $dados_celular['id'] = $celular_id;
        $dados_celular['imei'] = $input['imei'] ?? '';
        $dados_celular['serial_number'] = $input['serial_number'] ?? '';
        $dados_celular['propriedade'] = $input['propriedade'] ?? '';
        
        // Salvar metas do celular
        salvar_metas_celular($celular_id, $input);
        
        // Registrar auditoria
        registrar_auditoria('criar', 'celulares', $celular_id, null, $dados_celular);
        
        echo json_encode(['success' => true, 'celular_id' => $celular_id]);
        exit;
    }
    
    // Buscar dados do celular para edição
    if ($_GET['action'] === 'buscar_celular') {
        $celular_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        
        $q = "
            SELECT
                c.id,
                c.marca,
                c.modelo,
                c.status,
                c.colaborador AS colaborador_id,
                col.nome,
                col.sobrenome,
                col.matricula,
                imei.meta_value   AS imei,
                serial.meta_value AS serial_number,
                aquisicao.meta_value AS data_aquisicao,
                entrega.meta_value AS data_entrega,
                prop.meta_value AS propriedade,
                selb.meta_value AS selb,
                obs.meta_value AS observacao
            FROM {$tables->celulares} c
            LEFT JOIN {$tables->colaboradores} col
                ON col.id = c.colaborador
            LEFT JOIN {$tables->celulares_meta} imei
                ON imei.celular_id = c.id AND imei.meta_key = 'imei'
            LEFT JOIN {$tables->celulares_meta} serial
                ON serial.celular_id = c.id AND serial.meta_key = 'serial number'
            LEFT JOIN {$tables->celulares_meta} aquisicao
                ON aquisicao.celular_id = c.id AND aquisicao.meta_key = 'data_aquisicao'
            LEFT JOIN {$tables->celulares_meta} entrega
                ON entrega.celular_id = c.id AND entrega.meta_key = 'data_entrega'
            LEFT JOIN {$tables->celulares_meta} prop
                ON prop.celular_id = c.id AND prop.meta_key = 'propriedade'
            LEFT JOIN {$tables->celulares_meta} selb
                ON selb.celular_id = c.id AND selb.meta_key = 'selb'
            LEFT JOIN {$tables->celulares_meta} obs
                ON obs.celular_id = c.id AND obs.meta_key = 'observacao'
            WHERE c.id = %d
        ";
        
        $celular = $wpdb->get_row($wpdb->prepare($q, $celular_id), ARRAY_A);
        
        if ($celular) {
            echo json_encode(['success' => true, 'data' => $celular]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Celular não encontrado']);
        }
        exit;
    }
    
    // Atualizar celular
    if ($_GET['action'] === 'atualizar_celular' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $celular_id = isset($input['celular_id']) ? (int) $input['celular_id'] : 0;
        
        if (!$celular_id) {
            echo json_encode(['success' => false, 'message' => 'ID do celular inválido']);
            exit;
        }
        
        // Buscar dados anteriores do celular para auditoria
        $dados_anteriores = $wpdb->get_row($wpdb->prepare(
            "SELECT c.*, 
                   imei.meta_value AS imei,
                   serial.meta_value AS serial_number,
                   prop.meta_value AS propriedade,
                   obs.meta_value AS observacao
            FROM {$tables->celulares} c
            LEFT JOIN {$tables->celulares_meta} imei ON imei.celular_id = c.id AND imei.meta_key = 'imei'
            LEFT JOIN {$tables->celulares_meta} serial ON serial.celular_id = c.id AND serial.meta_key = 'serial number'
            LEFT JOIN {$tables->celulares_meta} prop ON prop.celular_id = c.id AND prop.meta_key = 'propriedade'
            LEFT JOIN {$tables->celulares_meta} obs ON obs.celular_id = c.id AND obs.meta_key = 'observacao'
            WHERE c.id = %d",
            $celular_id
        ), ARRAY_A);
        
        $colaborador_anterior = $dados_anteriores['colaborador'] ?? null;
        
        // Determinar colaborador (novo ou existente)
        // Se novo_colaborador está marcado, criar novo
        // Se colaborador_id está presente e não vazio, usar ele
        // Caso contrário, null (sem colaborador - devolução)
        if (!empty($input['novo_colaborador'])) {
            $colaborador_id = criar_colaborador($input);
        } elseif (isset($input['colaborador_id']) && $input['colaborador_id'] !== '' && $input['colaborador_id'] !== null) {
            $colaborador_id = (int) $input['colaborador_id'];
        } else {
            $colaborador_id = null;
        }
        
        // Preparar dados novos
        $dados_novos = [
            'id' => $celular_id,
            'marca' => sanitize_text_field($input['marca']),
            'modelo' => sanitize_text_field($input['modelo']),
            'colaborador' => $colaborador_id,
            'status' => sanitize_text_field($input['status']),
            'imei' => $input['imei'] ?? '',
            'serial_number' => $input['serial_number'] ?? '',
            'propriedade' => $input['propriedade'] ?? '',
            'observacao' => $input['observacao'] ?? ''
        ];
        
        // Atualizar dados principais do celular
        $wpdb->update(
            $tables->celulares,
            [
                'marca' => $dados_novos['marca'],
                'modelo' => $dados_novos['modelo'],
                'colaborador' => $colaborador_id,
                'status' => $dados_novos['status']
            ],
            ['id' => $celular_id]
        );
        
        // Atualizar ou inserir metas
        upsert_metas_celular($celular_id, $input);
        
        // Registrar auditoria
        registrar_auditoria('atualizar', 'celulares', $celular_id, $dados_anteriores, $dados_novos);
        
        // Verificar se houve mudança de colaborador
        $transferencia_id = null;
        $pdfs = [];
        
        if ($colaborador_anterior != $colaborador_id) {
            $transferencia_id = registrar_transferencia(
                $celular_id, 
                $colaborador_anterior ? (int) $colaborador_anterior : null, 
                $colaborador_id
            );
            
            // Buscar URLs dos PDFs gerados
            if ($transferencia_id) {
                $transf = $wpdb->get_row($wpdb->prepare(
                    "SELECT pdf_recebimento, pdf_devolucao FROM {$tables->transferencias} WHERE id = %d",
                    $transferencia_id
                ), ARRAY_A);
                
                if ($transf) {
                    $pdfs = [
                        'recebimento' => $transf['pdf_recebimento'],
                        'devolucao' => $transf['pdf_devolucao']
                    ];
                }
            }
        }
        
        echo json_encode([
            'success' => true, 
            'celular_id' => $celular_id,
            'transferencia' => $transferencia_id ? true : false,
            'pdfs' => $pdfs
        ]);
        exit;
    }
    
    // Buscar histórico de transferências
    if ($_GET['action'] === 'buscar_transferencias') {
        $celular_id = isset($_GET['celular_id']) ? (int) $_GET['celular_id'] : 0;
        
        if (!$celular_id) {
            echo json_encode(['success' => false, 'message' => 'ID do celular inválido']);
            exit;
        }
        
        $query = "
            SELECT 
                t.*,
                ca.nome AS nome_anterior,
                ca.sobrenome AS sobrenome_anterior,
                cn.nome AS nome_novo,
                cn.sobrenome AS sobrenome_novo
            FROM {$tables->transferencias} t
            LEFT JOIN {$tables->colaboradores} ca ON ca.id = t.colaborador_anterior
            LEFT JOIN {$tables->colaboradores} cn ON cn.id = t.colaborador_novo
            WHERE t.celular_id = %d
            ORDER BY t.data_transferencia DESC
        ";
        
        $transferencias = $wpdb->get_results($wpdb->prepare($query, $celular_id), ARRAY_A);
        
        echo json_encode(['success' => true, 'data' => $transferencias]);
        exit;
    }
    
    // Verificar IMEI duplicado
    if ($_GET['action'] === 'verificar_imei') {
        $imei = isset($_GET['imei']) ? sanitize_text_field($_GET['imei']) : '';
        $celular_id_atual = isset($_GET['celular_id']) ? (int) $_GET['celular_id'] : 0;
        
        if (empty($imei)) {
            echo json_encode(['success' => true, 'disponivel' => true]);
            exit;
        }
        
        $query = "
            SELECT c.id, c.marca, c.modelo 
            FROM {$tables->celulares_meta} cm
            INNER JOIN {$tables->celulares} c ON c.id = cm.celular_id
            WHERE cm.meta_key = 'imei' AND cm.meta_value = %s
        ";
        
        if ($celular_id_atual > 0) {
            $query .= " AND c.id != %d";
            $resultado = $wpdb->get_row($wpdb->prepare($query, $imei, $celular_id_atual), ARRAY_A);
        } else {
            $resultado = $wpdb->get_row($wpdb->prepare($query, $imei), ARRAY_A);
        }
        
        if ($resultado) {
            echo json_encode([
                'success' => true, 
                'disponivel' => false,
                'celular' => $resultado
            ]);
        } else {
            echo json_encode(['success' => true, 'disponivel' => true]);
        }
        exit;
    }
    
    // Buscar logs de auditoria
    if ($_GET['action'] === 'buscar_auditoria') {
        $tabela = isset($_GET['tabela']) ? sanitize_text_field($_GET['tabela']) : '';
        $registro_id = isset($_GET['registro_id']) ? (int) $_GET['registro_id'] : 0;
        $limite = isset($_GET['limite']) ? (int) $_GET['limite'] : 50;
        
        $where = [];
        $params = [];
        
        if (!empty($tabela)) {
            $where[] = "tabela = %s";
            $params[] = $tabela;
        }
        
        if ($registro_id > 0) {
            $where[] = "registro_id = %d";
            $params[] = $registro_id;
        }
        
        $where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $query = "
            SELECT *
            FROM {$tables->auditoria}
            {$where_clause}
            ORDER BY data_hora DESC
            LIMIT %d
        ";
        
        $params[] = $limite;
        
        $logs = $wpdb->get_results($wpdb->prepare($query, ...$params), ARRAY_A);
        
        echo json_encode(['success' => true, 'data' => $logs]);
        exit;
    }
}

// --- Inicialização do schema ---
ensure_schema();
handle_ajax();

// --- (Opcional) Semeadura mínima quando vazio para facilitar MVP ---
function seed_if_empty(): void {
    global $wpdb, $tables;

    $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$tables->celulares}");
    if ($count > 0) { return; }

    // Insere colaborador
    $wpdb->insert($tables->colaboradores, [
        'nome' => 'Joao', 'sobrenome' => 'Silva', 'matricula' => 'A123'
    ]);
    $colabId = (int) $wpdb->insert_id;

    // Metas do colaborador
    $wpdb->insert($tables->colaboradores_meta, [
        'colaborador_id' => $colabId, 'meta_key' => 'setor', 'meta_value' => 'Operacoes'
    ]);
    $wpdb->insert($tables->colaboradores_meta, [
        'colaborador_id' => $colabId, 'meta_key' => 'local', 'meta_value' => 'Sao Paulo'
    ]);

    // Insere celular
    $wpdb->insert($tables->celulares, [
        'marca' => 'Samsung', 'modelo' => 'A54', 'colaborador' => $colabId, 'status' => 'emprestado'
    ]);
    $celId = (int) $wpdb->insert_id;

    // Metas do celular
    $wpdb->insert($tables->celulares_meta, [
        'celular_id' => $celId, 'meta_key' => 'imei', 'meta_value' => '359999999999999'
    ]);
    $wpdb->insert($tables->celulares_meta, [
        'celular_id' => $celId, 'meta_key' => 'serial number', 'meta_value' => 'SN-XYZ-001'
    ]);
}
seed_if_empty();

// --- Carrega dados para renderização ---
$data = fetch_rows();
$dashboard_stats = fetch_dashboard_stats();
?>
<!doctype html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <title>Controle de Celulares - MVP</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap 5 via CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- jQuery (necessário para Bootbox.js) -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <!-- Bootbox.js via CDN -->
    <script src="https://cdn.jsdelivr.net/npm/bootbox@6.0.0/dist/bootbox.all.min.js"></script>
    <style>
        body { padding: 24px; background: #f8f9fa; }
        .table thead th { white-space: nowrap; }
        .status-badge { text-transform: capitalize; }
        .search-input { max-width: 360px; }
        .chip {
            display:inline-block; padding:.25rem .5rem; border-radius:999px; background:#f1f3f5; font-size:.8rem;
        }
        .muted { color:#6c757d; }
        
        /* Dashboard KPI Cards */
        .kpi-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.08);
            transition: transform 0.2s, box-shadow 0.2s;
            border-left: 4px solid;
        }
        .kpi-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.12);
        }
        .kpi-card.primary { border-left-color: #0d6efd; }
        .kpi-card.success { border-left-color: #198754; }
        .kpi-card.warning { border-left-color: #ffc107; }
        .kpi-card.danger { border-left-color: #dc3545; }
        .kpi-card.info { border-left-color: #0dcaf0; }
        .kpi-card.secondary { border-left-color: #6c757d; }
        
        .kpi-value {
            font-size: 2.5rem;
            font-weight: 700;
            line-height: 1;
            margin: 10px 0;
        }
        .kpi-label {
            color: #6c757d;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }
        .kpi-icon {
            width: 48px;
            height: 48px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 12px;
        }
        .kpi-icon.primary { background: #e7f1ff; color: #0d6efd; }
        .kpi-icon.success { background: #d1e7dd; color: #198754; }
        .kpi-icon.warning { background: #fff3cd; color: #ffc107; }
        .kpi-icon.danger { background: #f8d7da; color: #dc3545; }
        .kpi-icon.info { background: #cff4fc; color: #0dcaf0; }
        .kpi-icon.secondary { background: #e2e3e5; color: #6c757d; }
        
        .kpi-footer {
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid #e9ecef;
            font-size: 0.8rem;
            color: #6c757d;
        }
        .dashboard-section {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.08);
        }
    </style>
</head>
<body>
<div class="container-fluid">
    <!-- Alerta de Ambiente de Testes -->
    <div class="alert alert-warning alert-dismissible fade show mb-3" role="alert">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-exclamation-triangle-fill me-2" viewBox="0 0 16 16" style="vertical-align: middle;">
            <path d="M8.982 1.566a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767L8.982 1.566zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5zm.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2z"/>
        </svg>
        <strong>Ambiente de Testes</strong>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
    </div>
    
    <header class="d-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 m-0">Controle de Celulares</h1>
        <div class="d-flex gap-2 align-items-center">
            <button type="button" class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#modalDashboard">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                    <path d="M6 1H1v3h5V1zm9 0h-5v5h5V1zm0 6h-5v5h5V7zM6 8H1v7h5V8z"/>
                    <path d="M1 0a1 1 0 0 0-1 1v3a1 1 0 0 0 1 1h5a1 1 0 0 0 1-1V1a1 1 0 0 0-1-1H1zm0 7a1 1 0 0 0-1 1v7a1 1 0 0 0 1 1h5a1 1 0 0 0 1-1V8a1 1 0 0 0-1-1H1zm9-7a1 1 0 0 0-1 1v5a1 1 0 0 0 1 1h5a1 1 0 0 0 1-1V1a1 1 0 0 0-1-1h-5zm0 7a1 1 0 0 0-1 1v5a1 1 0 0 0 1 1h5a1 1 0 0 0 1-1V8a1 1 0 0 0-1-1h-5z"/>
                </svg>
                Dashboard
            </button>
            <button type="button" class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#modalAuditoria">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                    <path d="M8 16a2 2 0 0 0 2-2H6a2 2 0 0 0 2 2zM8 1.918l-.797.161A4.002 4.002 0 0 0 4 6c0 .628-.134 2.197-.459 3.742-.16.767-.376 1.566-.663 2.258h10.244c-.287-.692-.502-1.49-.663-2.258C12.134 8.197 12 6.628 12 6a4.002 4.002 0 0 0-3.203-3.92L8 1.917zM14.22 12c.223.447.481.801.78 1H1c.299-.199.557-.553.78-1C2.68 10.2 3 6.88 3 6c0-2.42 1.72-4.44 4.005-4.901a1 1 0 1 1 1.99 0A5.002 5.002 0 0 1 13 6c0 .88.32 4.2 1.22 6z"/>
                </svg>
                Auditoria
            </button>
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalAdicionarCelular">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-plus-lg" viewBox="0 0 16 16" style="vertical-align: middle; margin-right: 4px;">
                    <path fill-rule="evenodd" d="M8 2a.5.5 0 0 1 .5.5v5h5a.5.5 0 0 1 0 1h-5v5a.5.5 0 0 1-1 0v-5h-5a.5.5 0 0 1 0-1h5v-5A.5.5 0 0 1 8 2"/>
                </svg>Adicionar Celular
            </button>
            <input id="search" type="search" class="form-control form-control-sm search-input" placeholder="Pesquisar...">
            <span class="chip">Tabelas: <?php echo esc($tables->celulares); ?>, <?php echo esc($tables->celulares_meta); ?>, <?php echo esc($tables->colaboradores); ?>, <?php echo esc($tables->colaboradores_meta); ?>, <?php echo esc($tables->transferencias); ?>, <?php echo esc($tables->auditoria); ?></span>
            <span class="badge bg-secondary">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16" style="vertical-align: middle;">
                    <path d="M8 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6zm2-3a2 2 0 1 1-4 0 2 2 0 0 1 4 0zm4 8c0 1-1 1-1 1H3s-1 0-1-1 1-4 6-4 6 3 6 4zm-1-.004c-.001-.246-.154-.986-.832-1.664C11.516 10.68 10.289 10 8 10c-2.29 0-3.516.68-4.168 1.332-.678.678-.83 1.418-.832 1.664h10z"/>
                </svg>
                <?php echo esc($current_user->display_name); ?>
            </span>
            <a href="<?php echo wp_logout_url($_SERVER['PHP_SELF']); ?>" class="btn btn-outline-danger btn-sm" title="Sair">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
                    <path fill-rule="evenodd" d="M10 12.5a.5.5 0 0 1-.5.5h-8a.5.5 0 0 1-.5-.5v-9a.5.5 0 0 1 .5-.5h8a.5.5 0 0 1 .5.5v2a.5.5 0 0 0 1 0v-2A1.5 1.5 0 0 0 9.5 2h-8A1.5 1.5 0 0 0 0 3.5v9A1.5 1.5 0 0 0 1.5 14h8a1.5 1.5 0 0 0 1.5-1.5v-2a.5.5 0 0 0-1 0v2z"/>
                    <path fill-rule="evenodd" d="M15.854 8.354a.5.5 0 0 0 0-.708l-3-3a.5.5 0 0 0-.708.708L14.293 7.5H5.5a.5.5 0 0 0 0 1h8.793l-2.147 2.146a.5.5 0 0 0 .708.708l3-3z"/>
                </svg>
                Sair
            </a>
        </div>
    </header>

    <!-- Tabela de Inventário -->
    <div class="dashboard-section">
        <h5 class="mb-3">Inventário Completo</h5>
        <div class="table-responsive">
        <table id="grid" class="table table-striped table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>Marca</th>
                    <th>Modelo</th>
                    <th>IMEI</th>
                    <th>Serial</th>
                    <th>Status</th>
                    <th>Colaborador</th>
                    <th>Matrícula</th>
                    <th>Setor</th>
                    <th>Local</th>
                    <th>Histórico</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($data): ?>
                <?php foreach ($data as $r): ?>
                    <tr>
                        <td><?php echo esc($r['id']); ?></td>
                        <td><?php echo esc($r['marca']); ?></td>
                        <td><?php echo esc($r['modelo']); ?></td>
                        <td><code><?php echo esc($r['imei'] ?? ''); ?></code></td>
                        <td><code><?php echo esc($r['serial_number'] ?? ''); ?></code></td>
                        <td>
                            <span class="badge bg-<?php
                                $status = (string) ($r['status'] ?? '');
                                echo $status === 'disponivel' ? 'success' :
                                     ($status === 'emprestado' ? 'primary' :
                                     ($status === 'manutencao' ? 'warning' :
                                     ($status === 'defeito' ? 'danger' : 'secondary')));
                            ?> status-badge"><?php echo esc($status); ?></span>
                        </td>
                        <td>
                            <?php
                                $nomeCompleto = trim(($r['nome'] ?? '') . ' ' . ($r['sobrenome'] ?? ''));
                                echo $nomeCompleto ? esc($nomeCompleto) : '<span class="muted">—</span>';
                            ?>
                        </td>
                        <td><?php echo esc($r['matricula'] ?? ''); ?></td>
                        <td><?php echo esc($r['setor'] ?? ''); ?></td>
                        <td><?php echo esc($r['local_trabalho'] ?? ''); ?></td>
                        <td>
                            <button class="btn btn-sm btn-outline-info btn-historico" data-id="<?php echo esc($r['id']); ?>" title="Ver Histórico de Transferências">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
                                    <path d="M8.515 1.019A7 7 0 0 0 8 1V0a8 8 0 0 1 .589.022l-.074.997zm2.004.45a7.003 7.003 0 0 0-.985-.299l.219-.976c.383.086.76.2 1.126.342l-.36.933zm1.37.71a7.01 7.01 0 0 0-.439-.27l.493-.87a8.025 8.025 0 0 1 .979.654l-.615.789a6.996 6.996 0 0 0-.418-.302zm1.834 1.79a6.99 6.99 0 0 0-.653-.796l.724-.69c.27.285.52.59.747.91l-.818.576zm.744 1.352a7.08 7.08 0 0 0-.214-.468l.893-.45a7.976 7.976 0 0 1 .45 1.088l-.95.313a7.023 7.023 0 0 0-.179-.483zm.53 2.507a6.991 6.991 0 0 0-.1-1.025l.985-.17c.067.386.106.778.116 1.17l-1 .025zm-.131 1.538c.033-.17.06-.339.081-.51l.993.123a7.957 7.957 0 0 1-.23 1.155l-.964-.267c.046-.165.086-.332.12-.501zm-.952 2.379c.184-.29.346-.594.486-.908l.914.405c-.16.36-.345.706-.555 1.038l-.845-.535zm-.964 1.205c.122-.122.239-.248.35-.378l.758.653a8.073 8.073 0 0 1-.401.432l-.707-.707z"/>
                                    <path d="M8 1a7 7 0 1 0 4.95 11.95l.707.707A8.001 8.001 0 1 1 8 0v1z"/>
                                    <path d="M7.5 3a.5.5 0 0 1 .5.5v5.21l3.248 1.856a.5.5 0 0 1-.496.868l-3.5-2A.5.5 0 0 1 7 9V3.5a.5.5 0 0 1 .5-.5z"/>
                                </svg>
                            </button>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary btn-editar" data-id="<?php echo esc($r['id']); ?>" title="Editar">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
                                    <path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168l10-10zM11.207 2.5 13.5 4.793 14.793 3.5 12.5 1.207 11.207 2.5zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.293l6.5-6.5zm-9.761 5.175-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325z"/>
                                </svg>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="12" class="text-center text-muted">Nenhum registro.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>

    <!-- Modal Dashboard KPIs -->
    <div class="modal fade" id="modalDashboard" tabindex="-1" aria-labelledby="modalDashboardLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-light">
                    <h5 class="modal-title" id="modalDashboardLabel">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16" style="vertical-align: middle; margin-right: 8px;">
                            <path d="M6 1H1v3h5V1zm9 0h-5v5h5V1zm0 6h-5v5h5V7zM6 8H1v7h5V8z"/>
                            <path d="M1 0a1 1 0 0 0-1 1v3a1 1 0 0 0 1 1h5a1 1 0 0 0 1-1V1a1 1 0 0 0-1-1H1zm0 7a1 1 0 0 0-1 1v7a1 1 0 0 0 1 1h5a1 1 0 0 0 1-1V8a1 1 0 0 0-1-1H1zm9-7a1 1 0 0 0-1 1v5a1 1 0 0 0 1 1h5a1 1 0 0 0 1-1V1a1 1 0 0 0-1-1h-5zm0 7a1 1 0 0 0-1 1v5a1 1 0 0 0 1 1h5a1 1 0 0 0 1-1V8a1 1 0 0 0-1-1h-5z"/>
                        </svg>
                        Dashboard - Indicadores do Sistema
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body" style="background: #f8f9fa;">
                    <!-- Dashboard KPIs -->
                    <div class="row g-3 mb-3">
                        <!-- Total de Celulares -->
                        <div class="col-12 col-sm-6 col-lg-3">
                            <div class="kpi-card info">
                                <div class="kpi-icon info">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
                                        <path d="M11 1a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h6zM5 0a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2H5z"/>
                                        <path d="M8 14a1 1 0 1 0 0-2 1 1 0 0 0 0 2z"/>
                                    </svg>
                                </div>
                                <div class="kpi-label">Total de Celulares</div>
                                <div class="kpi-value text-info"><?php echo esc($dashboard_stats['total']); ?></div>
                                <div class="kpi-footer">
                                    <strong><?php echo esc($dashboard_stats['metalife']); ?></strong> Metalife • 
                                    <strong><?php echo esc($dashboard_stats['selbetti']); ?></strong> Selbetti
                                </div>
                            </div>
                        </div>
                        
                        <!-- Disponíveis -->
                        <div class="col-12 col-sm-6 col-lg-3">
                            <div class="kpi-card success">
                                <div class="kpi-icon success">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
                                        <path d="M10.97 4.97a.75.75 0 0 1 1.07 1.05l-3.99 4.99a.75.75 0 0 1-1.08.02L4.324 8.384a.75.75 0 1 1 1.06-1.06l2.094 2.093 3.473-4.425a.267.267 0 0 1 .02-.022z"/>
                                    </svg>
                                </div>
                                <div class="kpi-label">Disponíveis</div>
                                <div class="kpi-value text-success"><?php echo esc($dashboard_stats['disponivel']); ?></div>
                                <div class="kpi-footer">
                                    <?php 
                                    $perc_disp = $dashboard_stats['total'] > 0 ? round(($dashboard_stats['disponivel'] / $dashboard_stats['total']) * 100, 1) : 0;
                                    echo esc($perc_disp);
                                    ?>% do total
                                </div>
                            </div>
                        </div>
                        
                        <!-- Emprestados -->
                        <div class="col-12 col-sm-6 col-lg-3">
                            <div class="kpi-card primary">
                                <div class="kpi-icon primary">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
                                        <path d="M8 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6zm2-3a2 2 0 1 1-4 0 2 2 0 0 1 4 0zm4 8c0 1-1 1-1 1H3s-1 0-1-1 1-4 6-4 6 3 6 4zm-1-.004c-.001-.246-.154-.986-.832-1.664C11.516 10.68 10.289 10 8 10c-2.29 0-3.516.68-4.168 1.332-.678.678-.83 1.418-.832 1.664h10z"/>
                                    </svg>
                                </div>
                                <div class="kpi-label">Emprestados</div>
                                <div class="kpi-value text-primary"><?php echo esc($dashboard_stats['emprestado']); ?></div>
                                <div class="kpi-footer">
                                    <strong><?php echo esc($dashboard_stats['colaboradores_com_celular']); ?></strong> colaboradores ativos
                                </div>
                            </div>
                        </div>
                        
                        <!-- Em Manutenção -->
                        <div class="col-12 col-sm-6 col-lg-3">
                            <div class="kpi-card warning">
                                <div class="kpi-icon warning">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
                                        <path d="M9.293 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V4.707A1 1 0 0 0 13.707 4L10 .293A1 1 0 0 0 9.293 0zM9.5 3.5v-2l3 3h-2a1 1 0 0 1-1-1zM6.354 9.854a.5.5 0 0 1-.708-.708l2-2a.5.5 0 0 1 .708 0l2 2a.5.5 0 0 1-.708.708L8.5 8.707V12.5a.5.5 0 0 1-1 0V8.707L6.354 9.854z"/>
                                    </svg>
                                </div>
                                <div class="kpi-label">Em Manutenção</div>
                                <div class="kpi-value text-warning"><?php echo esc($dashboard_stats['manutencao']); ?></div>
                                <div class="kpi-footer">
                                    <?php if ($dashboard_stats['manutencao_longa'] > 0): ?>
                                        <span class="text-danger">⚠ <?php echo esc($dashboard_stats['manutencao_longa']); ?> há mais de 30 dias</span>
                                    <?php else: ?>
                                        Nenhum alerta
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Linha 2 de KPIs -->
                    <div class="row g-3">
                        <!-- Com Defeito -->
                        <div class="col-12 col-sm-6 col-lg-3">
                            <div class="kpi-card danger">
                                <div class="kpi-icon danger">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
                                        <path d="M8.982 1.566a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767L8.982 1.566zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5zm.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2z"/>
                                    </svg>
                                </div>
                                <div class="kpi-label">Com Defeito</div>
                                <div class="kpi-value text-danger"><?php echo esc($dashboard_stats['defeito']); ?></div>
                                <div class="kpi-footer">
                                    Requer atenção
                                </div>
                            </div>
                        </div>
                        
                        <!-- Inativos -->
                        <div class="col-12 col-sm-6 col-lg-3">
                            <div class="kpi-card secondary">
                                <div class="kpi-icon secondary">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
                                        <path d="M6 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6zm-5 6s-1 0-1-1 1-4 6-4 6 3 6 4-1 1-1 1H1zM11 3.5a.5.5 0 0 1 .5-.5h4a.5.5 0 0 1 0 1h-4a.5.5 0 0 1-.5-.5zm.5 2.5a.5.5 0 0 0 0 1h4a.5.5 0 0 0 0-1h-4zm2 3a.5.5 0 0 0 0 1h2a.5.5 0 0 0 0-1h-2z"/>
                                    </svg>
                                </div>
                                <div class="kpi-label">Inativos</div>
                                <div class="kpi-value text-secondary"><?php echo esc($dashboard_stats['inativo']); ?></div>
                                <div class="kpi-footer">
                                    Fora de circulação
                                </div>
                            </div>
                        </div>
                        
                        <!-- Total Colaboradores -->
                        <div class="col-12 col-sm-6 col-lg-3">
                            <div class="kpi-card info">
                                <div class="kpi-icon info">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
                                        <path d="M7 14s-1 0-1-1 1-4 5-4 5 3 5 4-1 1-1 1H7zm4-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"/>
                                        <path fill-rule="evenodd" d="M5.216 14A2.238 2.238 0 0 1 5 13c0-1.355.68-2.75 1.936-3.72A6.325 6.325 0 0 0 5 9c-4 0-5 3-5 4s1 1 1 1h4.216z"/>
                                        <path d="M4.5 8a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5z"/>
                                    </svg>
                                </div>
                                <div class="kpi-label">Colaboradores</div>
                                <div class="kpi-value text-info"><?php echo esc($dashboard_stats['total_colaboradores']); ?></div>
                                <div class="kpi-footer">
                                    <?php echo esc($dashboard_stats['colaboradores_com_celular']); ?> com celular ativo
                                </div>
                            </div>
                        </div>
                        
                        <!-- Transferências do Mês -->
                        <div class="col-12 col-sm-6 col-lg-3">
                            <div class="kpi-card primary">
                                <div class="kpi-icon primary">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
                                        <path d="M8.515 1.019A7 7 0 0 0 8 1V0a8 8 0 0 1 .589.022l-.074.997zm2.004.45a7.003 7.003 0 0 0-.985-.299l.219-.976c.383.086.76.2 1.126.342l-.36.933zm1.37.71a7.01 7.01 0 0 0-.439-.27l.493-.87a8.025 8.025 0 0 1 .979.654l-.615.789a6.996 6.996 0 0 0-.418-.302zm1.834 1.79a6.99 6.99 0 0 0-.653-.796l.724-.69c.27.285.52.59.747.91l-.818.576zm.744 1.352a7.08 7.08 0 0 0-.214-.468l.893-.45a7.976 7.976 0 0 1 .45 1.088l-.95.313a7.023 7.023 0 0 0-.179-.483zm.53 2.507a6.991 6.991 0 0 0-.1-1.025l.985-.17c.067.386.106.778.116 1.17l-1 .025zm-.131 1.538c.033-.17.06-.339.081-.51l.993.123a7.957 7.957 0 0 1-.23 1.155l-.964-.267c.046-.165.086-.332.12-.501zm-.952 2.379c.184-.29.346-.594.486-.908l.914.405c-.16.36-.345.706-.555 1.038l-.845-.535zm-.964 1.205c.122-.122.239-.248.35-.378l.758.653a8.073 8.073 0 0 1-.401.432l-.707-.707z"/>
                                        <path d="M8 1a7 7 0 1 0 4.95 11.95l.707.707A8.001 8.001 0 1 1 8 0v1z"/>
                                        <path d="M7.5 3a.5.5 0 0 1 .5.5v5.21l3.248 1.856a.5.5 0 0 1-.496.868l-3.5-2A.5.5 0 0 1 7 9V3.5a.5.5 0 0 1 .5-.5z"/>
                                    </svg>
                                </div>
                                <div class="kpi-label">Transferências (30d)</div>
                                <div class="kpi-value text-primary"><?php echo esc($dashboard_stats['transferencias_mes']); ?></div>
                                <div class="kpi-footer">
                                    Último mês
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Linha 3 de KPIs - Novos Status -->
                    <div class="row g-3 mt-2">
                        <!-- Perdidos -->
                        <div class="col-12 col-sm-6 col-lg-3">
                            <div class="kpi-card warning">
                                <div class="kpi-icon warning">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
                                        <path d="M8 16s6-5.686 6-10A6 6 0 0 0 2 6c0 4.314 6 10 6 10zm0-7a3 3 0 1 1 0-6 3 3 0 0 1 0 6z"/>
                                    </svg>
                                </div>
                                <div class="kpi-label">Perdidos</div>
                                <div class="kpi-value text-warning"><?php echo esc($dashboard_stats['perdido']); ?></div>
                                <div class="kpi-footer">
                                    Extraviados
                                </div>
                            </div>
                        </div>
                        
                        <!-- Roubados -->
                        <div class="col-12 col-sm-6 col-lg-3">
                            <div class="kpi-card danger">
                                <div class="kpi-icon danger">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
                                        <path d="M8 1a2 2 0 0 1 2 2v4H6V3a2 2 0 0 1 2-2zm3 6V3a3 3 0 0 0-6 0v4a2 2 0 0 0-2 2v5a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2z"/>
                                    </svg>
                                </div>
                                <div class="kpi-label">Roubados</div>
                                <div class="kpi-value text-danger"><?php echo esc($dashboard_stats['roubado']); ?></div>
                                <div class="kpi-footer">
                                    Requer B.O.
                                </div>
                            </div>
                        </div>
                        
                        <!-- Descartados -->
                        <div class="col-12 col-sm-6 col-lg-3">
                            <div class="kpi-card secondary">
                                <div class="kpi-icon secondary">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
                                        <path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0V6z"/>
                                        <path fill-rule="evenodd" d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1v1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4H4.118zM2.5 3V2h11v1h-11z"/>
                                    </svg>
                                </div>
                                <div class="kpi-label">Descartados</div>
                                <div class="kpi-value text-secondary"><?php echo esc($dashboard_stats['descartado']); ?></div>
                                <div class="kpi-footer">
                                    Baixados do patrimônio
                                </div>
                            </div>
                        </div>
                        
                        <!-- Total Geral -->
                        <div class="col-12 col-sm-6 col-lg-3">
                            <div class="kpi-card info">
                                <div class="kpi-icon info">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
                                        <path d="M11 5a3 3 0 1 1-6 0 3 3 0 0 1 6 0ZM8 7a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm.256 7a4.474 4.474 0 0 1-.229-1.004H3c.001-.246.154-.986.832-1.664C4.484 10.68 5.711 10 8 10c.26 0 .507.009.74.025.226-.341.496-.65.804-.918C9.077 9.038 8.564 9 8 9c-5 0-6 3-6 4s1 1 1 1h5.256Zm3.63-4.54c.18-.613 1.048-.613 1.229 0l.043.148a.64.64 0 0 0 .921.382l.136-.074c.561-.306 1.175.308.87.869l-.075.136a.64.64 0 0 0 .382.92l.149.045c.612.18.612 1.048 0 1.229l-.15.043a.64.64 0 0 0-.38.921l.074.136c.305.561-.309 1.175-.87.87l-.136-.075a.64.64 0 0 0-.92.382l-.045.149c-.18.612-1.048.612-1.229 0l-.043-.15a.64.64 0 0 0-.921-.38l-.136.074c-.561.305-1.175-.309-.87-.87l.075-.136a.64.64 0 0 0-.382-.92l-.148-.045c-.613-.18-.613-1.048 0-1.229l.148-.043a.64.64 0 0 0 .382-.921l-.074-.136c-.306-.561.308-1.175.869-.87l.136.075a.64.64 0 0 0 .92-.382l.045-.148ZM14 12.5a1.5 1.5 0 1 0-3 0 1.5 1.5 0 0 0 3 0Z"/>
                                    </svg>
                                </div>
                                <div class="kpi-label">Total de Celulares</div>
                                <div class="kpi-value text-info"><?php echo esc($dashboard_stats['total']); ?></div>
                                <div class="kpi-footer">
                                    Todos os registros
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Adicionar Celular -->
    <div class="modal fade" id="modalAdicionarCelular" tabindex="-1" aria-labelledby="modalAdicionarCelularLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalAdicionarCelularLabel">Adicionar Celular</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <input type="hidden" id="celular_id_edit" name="celular_id_edit">
                <div class="modal-body">
                    <form id="formAdicionarCelular">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="marca" class="form-label">Marca *</label>
                                <input type="text" class="form-control" id="marca" name="marca" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="modelo" class="form-label">Modelo *</label>
                                <input type="text" class="form-control" id="modelo" name="modelo" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="imei" class="form-label">IMEI</label>
                                <input type="text" class="form-control" id="imei" name="imei">
                                <div id="imei_feedback" class="form-text" style="display:none;"></div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="serial_number" class="form-label">Serial Number</label>
                                <input type="text" class="form-control" id="serial_number" name="serial_number">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="data_aquisicao" class="form-label">Data de Aquisição</label>
                                <input type="date" class="form-control" id="data_aquisicao" name="data_aquisicao">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="data_entrega" class="form-label">Data de Entrega ao Colaborador</label>
                                <input type="date" class="form-control" id="data_entrega" name="data_entrega">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="propriedade" class="form-label">Propriedade *</label>
                                <select class="form-select" id="propriedade" name="propriedade" required>
                                    <option value="">Selecione...</option>
                                    <option value="Metalife">Metalife</option>
                                    <option value="Selbetti">Selbetti</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="selb" class="form-label">SELB</label>
                                <input type="text" class="form-control" id="selb" name="selb" placeholder="Código SELB">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="status" class="form-label">Status *</label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="disponivel">Disponível</option>
                                <option value="emprestado">Emprestado</option>
                                <option value="manutencao">Manutenção</option>
                                <option value="defeito">Defeito</option>
                                <option value="inativo">Inativo</option>
                                <option value="perdido">Perdido</option>
                                <option value="roubado">Roubado</option>
                                <option value="descartado">Descartado</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="observacao" class="form-label">Observação</label>
                            <textarea class="form-control" id="observacao" name="observacao" rows="3" placeholder="Digite observações sobre o celular..."></textarea>
                        </div>
                        
                        <hr class="my-4">
                        <h6 class="mb-3">Dados do Colaborador</h6>
                        
                        <div class="mb-3">
                            <label for="busca_colaborador" class="form-label">Buscar Colaborador</label>
                            <input type="text" class="form-control" id="busca_colaborador" placeholder="Digite o nome ou matrícula..." autocomplete="off">
                            <input type="hidden" id="colaborador_id" name="colaborador_id">
                            <div id="status_busca" class="form-text text-muted mt-1" style="display:none;"></div>
                            <div id="lista_colaboradores" class="list-group mt-2" style="display:none; max-height: 200px; overflow-y: auto;"></div>
                        </div>
                        
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="novo_colaborador" name="novo_colaborador">
                            <label class="form-check-label" for="novo_colaborador">
                                Adicionar novo colaborador
                            </label>
                        </div>
                        
                        <div id="campos_novo_colaborador" style="display:none;">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="colaborador_nome" class="form-label">Nome *</label>
                                    <input type="text" class="form-control" id="colaborador_nome" name="colaborador_nome">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="colaborador_sobrenome" class="form-label">Sobrenome *</label>
                                    <input type="text" class="form-control" id="colaborador_sobrenome" name="colaborador_sobrenome">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="colaborador_matricula" class="form-label">Matrícula *</label>
                                    <input type="text" class="form-control" id="colaborador_matricula" name="colaborador_matricula">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="colaborador_setor" class="form-label">Setor</label>
                                    <input type="text" class="form-control" id="colaborador_setor" name="colaborador_setor">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="colaborador_local" class="form-label">Local</label>
                                    <input type="text" class="form-control" id="colaborador_local" name="colaborador_local">
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="btnSalvarCelular">Salvar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Histórico de Transferências -->
    <div class="modal fade" id="modalHistorico" tabindex="-1" aria-labelledby="modalHistoricoLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalHistoricoLabel">Histórico de Transferências</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <div id="historico-loading" class="text-center py-4" style="display:none;">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Carregando...</span>
                        </div>
                        <p class="mt-2 text-muted">Carregando histórico...</p>
                    </div>
                    <div id="historico-conteudo"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Auditoria -->
    <div class="modal fade" id="modalAuditoria" tabindex="-1" aria-labelledby="modalAuditoriaLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title" id="modalAuditoriaLabel">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16" style="vertical-align: middle; margin-right: 8px;">
                            <path d="M8 16a2 2 0 0 0 2-2H6a2 2 0 0 0 2 2zM8 1.918l-.797.161A4.002 4.002 0 0 0 4 6c0 .628-.134 2.197-.459 3.742-.16.767-.376 1.566-.663 2.258h10.244c-.287-.692-.502-1.49-.663-2.258C12.134 8.197 12 6.628 12 6a4.002 4.002 0 0 0-3.203-3.92L8 1.917zM14.22 12c.223.447.481.801.78 1H1c.299-.199.557-.553.78-1C2.68 10.2 3 6.88 3 6c0-2.42 1.72-4.44 4.005-4.901a1 1 0 1 1 1.99 0A5.002 5.002 0 0 1 13 6c0 .88.32 4.2 1.22 6z"/>
                        </svg>
                        Logs de Auditoria
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body" style="background: #f8f9fa;">
                    <!-- Filtros -->
                    <div class="card mb-3">
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label for="filtro_tabela" class="form-label">Tabela</label>
                                    <select class="form-select form-select-sm" id="filtro_tabela">
                                        <option value="">Todas</option>
                                        <option value="celulares">Celulares</option>
                                        <option value="colaboradores">Colaboradores</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="filtro_acao" class="form-label">Ação</label>
                                    <select class="form-select form-select-sm" id="filtro_acao">
                                        <option value="">Todas</option>
                                        <option value="criar">Criar</option>
                                        <option value="atualizar">Atualizar</option>
                                        <option value="excluir">Excluir</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="filtro_limite" class="form-label">Limite</label>
                                    <select class="form-select form-select-sm" id="filtro_limite">
                                        <option value="50">50 registros</option>
                                        <option value="100">100 registros</option>
                                        <option value="200">200 registros</option>
                                        <option value="500">500 registros</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mt-3">
                                <button type="button" class="btn btn-primary btn-sm" id="btnFiltrarAuditoria">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
                                        <path d="M6 10.5a.5.5 0 0 1 .5-.5h3a.5.5 0 0 1 0 1h-3a.5.5 0 0 1-.5-.5zm-2-3a.5.5 0 0 1 .5-.5h7a.5.5 0 0 1 0 1h-7a.5.5 0 0 1-.5-.5zm-2-3a.5.5 0 0 1 .5-.5h11a.5.5 0 0 1 0 1h-11a.5.5 0 0 1-.5-.5z"/>
                                    </svg>
                                    Filtrar
                                </button>
                                <button type="button" class="btn btn-secondary btn-sm" id="btnLimparFiltros">Limpar Filtros</button>
                            </div>
                        </div>
                    </div>
                    
                    <div id="auditoria-loading" class="text-center py-4" style="display:none;">
                        <div class="spinner-border text-warning" role="status">
                            <span class="visually-hidden">Carregando...</span>
                        </div>
                        <p class="mt-2 text-muted">Carregando logs de auditoria...</p>
                    </div>
                    <div id="auditoria-conteudo"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>
   
</div>

<!-- JS: Bootstrap e filtro simples local -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    const input = document.getElementById('search');
    const table = document.getElementById('grid');
    const rows = Array.from(table.tBodies[0].rows);

    function normalizar(t) { return t.toLowerCase(); }

    input.addEventListener('input', function () {
        const q = normalizar(this.value);
        rows.forEach(tr => {
            const text = normalizar(tr.innerText);
            tr.style.display = text.includes(q) ? '' : 'none';
        });
    });
})();

// Sistema de busca e adição de celular
(function() {
    const buscaInput = document.getElementById('busca_colaborador');
    const listaDiv = document.getElementById('lista_colaboradores');
    const colaboradorIdInput = document.getElementById('colaborador_id');
    const novoColabCheckbox = document.getElementById('novo_colaborador');
    const camposNovoColab = document.getElementById('campos_novo_colaborador');
    const btnSalvar = document.getElementById('btnSalvarCelular');
    const form = document.getElementById('formAdicionarCelular');
    const statusBusca = document.getElementById('status_busca');
    const imeiInput = document.getElementById('imei');
    const imeiFeedback = document.getElementById('imei_feedback');
    let timeoutBusca = null;
    let timeoutImei = null;
    let imeiValido = true;
    
    // Validação de IMEI
    imeiInput.addEventListener('input', function() {
        const imei = this.value.trim();
        
        clearTimeout(timeoutImei);
        
        if (imei.length === 0) {
            imeiFeedback.style.display = 'none';
            imeiInput.classList.remove('is-invalid', 'is-valid');
            imeiValido = true;
            return;
        }
        
        timeoutImei = setTimeout(() => {
            const celularIdEdit = document.getElementById('celular_id_edit').value;
            const url = `?action=verificar_imei&imei=${encodeURIComponent(imei)}${celularIdEdit ? '&celular_id=' + celularIdEdit : ''}`;
            
            fetch(url)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        if (data.disponivel) {
                            imeiInput.classList.remove('is-invalid');
                            imeiInput.classList.add('is-valid');
                            imeiFeedback.className = 'form-text text-success';
                            imeiFeedback.innerHTML = '✓ IMEI disponível';
                            imeiFeedback.style.display = 'block';
                            imeiValido = true;
                        } else {
                            imeiInput.classList.remove('is-valid');
                            imeiInput.classList.add('is-invalid');
                            imeiFeedback.className = 'form-text text-danger';
                            imeiFeedback.innerHTML = `✗ IMEI já cadastrado no celular: ${data.celular.marca} ${data.celular.modelo} (ID: ${data.celular.id})`;
                            imeiFeedback.style.display = 'block';
                            imeiValido = false;
                        }
                    }
                })
                .catch(err => {
                    console.error('Erro ao verificar IMEI:', err);
                });
        }, 500);
    });
    
    // Toggle campos de novo colaborador
    novoColabCheckbox.addEventListener('change', function() {
        if (this.checked) {
            camposNovoColab.style.display = 'block';
            buscaInput.disabled = true;
            colaboradorIdInput.value = '';
            listaDiv.style.display = 'none';
            // Tornar campos obrigatórios
            document.getElementById('colaborador_nome').required = true;
            document.getElementById('colaborador_sobrenome').required = true;
            document.getElementById('colaborador_matricula').required = true;
        } else {
            camposNovoColab.style.display = 'none';
            buscaInput.disabled = false;
            // Remover obrigatoriedade
            document.getElementById('colaborador_nome').required = false;
            document.getElementById('colaborador_sobrenome').required = false;
            document.getElementById('colaborador_matricula').required = false;
        }
    });
    
    // Busca de colaboradores
    buscaInput.addEventListener('input', function() {
        const termo = this.value.trim();
        
        clearTimeout(timeoutBusca);
        
        // Se o campo foi limpo, limpar o colaborador_id também
        if (termo.length === 0) {
            colaboradorIdInput.value = '';
            listaDiv.style.display = 'none';
            statusBusca.style.display = 'none';
            return;
        }
        
        if (termo.length < 2) {
            listaDiv.style.display = 'none';
            statusBusca.style.display = 'none';
            return;
        }
        
        timeoutBusca = setTimeout(() => {
            statusBusca.innerHTML = `
                <span class="d-inline-flex align-items-center gap-2 text-muted">
                    <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                    Pesquisando colaboradores...
                </span>`;
            statusBusca.style.display = 'block';
            listaDiv.style.display = 'none';
            fetch(`?action=buscar_colaboradores&termo=${encodeURIComponent(termo)}`)
                .then(res => res.json())
                .then(data => {
                    statusBusca.style.display = 'none';
                    if (data.success && data.data.length > 0) {
                        listaDiv.innerHTML = data.data.map(c => 
                            `<a href="#" class="list-group-item list-group-item-action" data-id="${c.id}" data-nome="${c.nome} ${c.sobrenome}">
                                <strong>${c.nome} ${c.sobrenome}</strong> - ${c.matricula}
                            </a>`
                        ).join('');
                        listaDiv.style.display = 'block';
                    } else {
                        listaDiv.innerHTML = '<div class="list-group-item text-muted">Nenhum colaborador encontrado</div>';
                        listaDiv.style.display = 'block';
                    }
                })
                .catch(err => console.error('Erro ao buscar colaboradores:', err));
        }, 300);
    });
    
    // Selecionar colaborador da lista
    listaDiv.addEventListener('click', function(e) {
        e.preventDefault();
        const item = e.target.closest('.list-group-item-action');
        if (item) {
            colaboradorIdInput.value = item.dataset.id;
            buscaInput.value = item.dataset.nome;
            listaDiv.style.display = 'none';
        }
    });
    
    // Salvar celular
    btnSalvar.addEventListener('click', function() {
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }
        
        // Verificar se IMEI é válido
        if (!imeiValido && imeiInput.value.trim() !== '') {
            bootbox.alert({
                message: 'O IMEI informado já está cadastrado em outro celular. Por favor, verifique o IMEI.',
                callback: function() {
                    imeiInput.focus();
                }
            });
            return;
        }
        
        const formData = new FormData(form);
        const data = {};
        formData.forEach((value, key) => {
            if (key === 'novo_colaborador') {
                data[key] = novoColabCheckbox.checked;
            } else {
                data[key] = value;
            }
        });
        
        const celularIdEdit = document.getElementById('celular_id_edit').value;
        const isEdit = !!celularIdEdit;
        
        if (isEdit) {
            data.celular_id = celularIdEdit;
        }
        
        btnSalvar.disabled = true;
        btnSalvar.textContent = 'Salvando...';
        
        const action = isEdit ? 'atualizar_celular' : 'salvar_celular';
        
        fetch(`?action=${action}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        })
        .then(res => res.json())
        .then(result => {
            if (result.success) {
                let mensagem = isEdit ? 'Celular atualizado com sucesso!' : 'Celular adicionado com sucesso!';
                
                // Se houve transferência, mostrar links para os PDFs
                if (result.transferencia && result.pdfs) {
                    mensagem += '<br><br><strong>Fichas geradas:</strong><br>';
                    
                    if (result.pdfs.recebimento) {
                        mensagem += `<a href="${result.pdfs.recebimento}" target="_blank" class="btn btn-sm btn-primary mt-2">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                <path d="M8.5 6.5a.5.5 0 0 0-1 0v3.793L6.354 9.146a.5.5 0 1 0-.708.708l2 2a.5.5 0 0 0 .708 0l2-2a.5.5 0 0 0-.708-.708L8.5 10.293V6.5z"/>
                                <path d="M14 14V4.5L9.5 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2zM9.5 3A1.5 1.5 0 0 0 11 4.5h2V14a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h5.5v2z"/>
                            </svg>
                            Ficha de Recebimento
                        </a><br>`;
                    }
                    
                    if (result.pdfs.devolucao) {
                        mensagem += `<a href="${result.pdfs.devolucao}" target="_blank" class="btn btn-sm btn-warning mt-2">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                <path d="M8.5 6.5a.5.5 0 0 0-1 0v3.793L6.354 9.146a.5.5 0 1 0-.708.708l2 2a.5.5 0 0 0 .708 0l2-2a.5.5 0 0 0-.708-.708L8.5 10.293V6.5z"/>
                                <path d="M14 14V4.5L9.5 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2zM9.5 3A1.5 1.5 0 0 0 11 4.5h2V14a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h5.5v2z"/>
                            </svg>
                            Ficha de Devolução
                        </a>`;
                    }
                }
                
                bootbox.alert({
                    message: mensagem,
                    callback: function() {
                        location.reload();
                    }
                });
            } else {
                bootbox.alert('Erro ao salvar celular');
                btnSalvar.disabled = false;
                btnSalvar.textContent = 'Salvar';
            }
        })
        .catch(err => {
            console.error('Erro:', err);
            bootbox.alert('Erro ao salvar celular');
            btnSalvar.disabled = false;
            btnSalvar.textContent = 'Salvar';
        });
    });
    
    // Limpar formulário ao fechar modal
    document.getElementById('modalAdicionarCelular').addEventListener('hidden.bs.modal', function() {
        form.reset();
        colaboradorIdInput.value = '';
        document.getElementById('celular_id_edit').value = '';
        document.getElementById('modalAdicionarCelularLabel').textContent = 'Adicionar Celular';
        listaDiv.style.display = 'none';
        statusBusca.style.display = 'none';
        camposNovoColab.style.display = 'none';
        novoColabCheckbox.checked = false;
        buscaInput.disabled = false;
        btnSalvar.disabled = false;
        btnSalvar.textContent = 'Salvar';
        // Limpar validação de IMEI
        imeiFeedback.style.display = 'none';
        imeiInput.classList.remove('is-invalid', 'is-valid');
        imeiValido = true;
    });
})();

// Ver histórico de transferências
document.addEventListener('click', function(e) {
    if (e.target.closest('.btn-historico')) {
        const btn = e.target.closest('.btn-historico');
        const celularId = btn.dataset.id;
        
        const modal = new bootstrap.Modal(document.getElementById('modalHistorico'));
        const loading = document.getElementById('historico-loading');
        const conteudo = document.getElementById('historico-conteudo');
        
        loading.style.display = 'block';
        conteudo.innerHTML = '';
        modal.show();
        
        fetch(`?action=buscar_transferencias&celular_id=${celularId}`)
            .then(res => res.json())
            .then(result => {
                loading.style.display = 'none';
                
                if (result.success && result.data && result.data.length > 0) {
                    let html = '<div class="table-responsive"><table class="table table-hover">';
                    html += '<thead class="table-light"><tr>';
                    html += '<th>Data</th><th>De</th><th>Para</th><th>Fichas</th>';
                    html += '</tr></thead><tbody>';
                    
                    result.data.forEach(t => {
                        const data = new Date(t.data_transferencia).toLocaleString('pt-BR');
                        const de = t.nome_anterior ? `${t.nome_anterior} ${t.sobrenome_anterior}` : '<span class="text-muted">Estoque</span>';
                        const para = t.nome_novo ? `${t.nome_novo} ${t.sobrenome_novo}` : '<span class="text-muted">Estoque</span>';
                        
                        html += `<tr><td>${data}</td><td>${de}</td><td>${para}</td><td>`;
                        
                        if (t.pdf_recebimento) {
                            html += `<a href="${t.pdf_recebimento}" target="_blank" class="btn btn-sm btn-primary me-1">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
                                    <path d="M.5 9.9a.5.5 0 0 1 .5.5v2.5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.5a.5.5 0 0 1 1 0v2.5a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2v-2.5a.5.5 0 0 1 .5-.5z"/>
                                    <path d="M7.646 11.854a.5.5 0 0 0 .708 0l3-3a.5.5 0 0 0-.708-.708L8.5 10.293V1.5a.5.5 0 0 0-1 0v8.793L5.354 8.146a.5.5 0 1 0-.708.708l3 3z"/>
                                </svg>
                                Recebimento
                            </a>`;
                        }
                        
                        if (t.pdf_devolucao) {
                            html += `<a href="${t.pdf_devolucao}" target="_blank" class="btn btn-sm btn-warning">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
                                    <path d="M.5 9.9a.5.5 0 0 1 .5.5v2.5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.5a.5.5 0 0 1 1 0v2.5a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2v-2.5a.5.5 0 0 1 .5-.5z"/>
                                    <path d="M7.646 11.854a.5.5 0 0 0 .708 0l3-3a.5.5 0 0 0-.708-.708L8.5 10.293V1.5a.5.5 0 0 0-1 0v8.793L5.354 8.146a.5.5 0 1 0-.708.708l3 3z"/>
                                </svg>
                                Devolução
                            </a>`;
                        }
                        
                        if (!t.pdf_recebimento && !t.pdf_devolucao) {
                            html += '<span class="text-muted">Sem fichas</span>';
                        }
                        
                        html += '</td></tr>';
                    });
                    
                    html += '</tbody></table></div>';
                    conteudo.innerHTML = html;
                } else {
                    conteudo.innerHTML = '<div class="alert alert-info">Nenhuma transferência registrada para este celular.</div>';
                }
            })
            .catch(err => {
                loading.style.display = 'none';
                conteudo.innerHTML = '<div class="alert alert-danger">Erro ao carregar histórico.</div>';
                console.error('Erro:', err);
            });
    }
});

// Editar celular
document.addEventListener('click', function(e) {
    if (e.target.closest('.btn-editar')) {
        const btn = e.target.closest('.btn-editar');
        const celularId = btn.dataset.id;
        
        fetch(`?action=buscar_celular&id=${celularId}`)
            .then(res => res.json())
            .then(result => {
                if (result.success && result.data) {
                    const d = result.data;
                    
                    // Preencher campos
                    document.getElementById('celular_id_edit').value = d.id;
                    document.getElementById('marca').value = d.marca || '';
                    document.getElementById('modelo').value = d.modelo || '';
                    document.getElementById('imei').value = d.imei || '';
                    document.getElementById('serial_number').value = d.serial_number || '';
                    document.getElementById('data_aquisicao').value = d.data_aquisicao || '';
                    document.getElementById('data_entrega').value = d.data_entrega || '';
                    document.getElementById('propriedade').value = d.propriedade || '';
                    document.getElementById('selb').value = d.selb || '';
                    document.getElementById('observacao').value = d.observacao || '';
                    document.getElementById('status').value = d.status || 'disponivel';
                    
                    // Preencher colaborador se existir
                    if (d.colaborador_id) {
                        document.getElementById('colaborador_id').value = d.colaborador_id;
                        document.getElementById('busca_colaborador').value = `${d.nome || ''} ${d.sobrenome || ''}`.trim();
                    }
                    
                    // Alterar título do modal
                    document.getElementById('modalAdicionarCelularLabel').textContent = 'Editar Celular';
                    
                    // Abrir modal
                    const modal = new bootstrap.Modal(document.getElementById('modalAdicionarCelular'));
                    modal.show();
                } else {
                    bootbox.alert('Erro ao carregar dados do celular');
                }
            })
            .catch(err => {
                console.error('Erro:', err);
                bootbox.alert('Erro ao carregar dados do celular');
            });
    }
});

// Sistema de Auditoria
(function() {
    const modalAuditoria = document.getElementById('modalAuditoria');
    const btnFiltrar = document.getElementById('btnFiltrarAuditoria');
    const btnLimpar = document.getElementById('btnLimparFiltros');
    const loading = document.getElementById('auditoria-loading');
    const conteudo = document.getElementById('auditoria-conteudo');
    
    function carregarAuditoria() {
        const tabela = document.getElementById('filtro_tabela').value;
        const acao = document.getElementById('filtro_acao').value;
        const limite = document.getElementById('filtro_limite').value;
        
        let url = '?action=buscar_auditoria';
        const params = [];
        
        if (tabela) params.push(`tabela=${encodeURIComponent(tabela)}`);
        if (limite) params.push(`limite=${encodeURIComponent(limite)}`);
        
        if (params.length > 0) {
            url += '&' + params.join('&');
        }
        
        loading.style.display = 'block';
        conteudo.innerHTML = '';
        
        fetch(url)
            .then(res => res.json())
            .then(result => {
                loading.style.display = 'none';
                
                if (result.success && result.data && result.data.length > 0) {
                    let logs = result.data;
                    
                    // Filtrar por ação no frontend (já que o backend não filtra)
                    if (acao) {
                        logs = logs.filter(log => log.acao === acao);
                    }
                    
                    if (logs.length === 0) {
                        conteudo.innerHTML = '<div class="alert alert-info">Nenhum log encontrado com os filtros selecionados.</div>';
                        return;
                    }
                    
                    let html = '<div class="accordion" id="accordionAuditoria">';
                    
                    logs.forEach((log, index) => {
                        const data = new Date(log.data_hora).toLocaleString('pt-BR');
                        const acaoBadge = log.acao === 'criar' ? 'success' : (log.acao === 'atualizar' ? 'warning' : 'danger');
                        const tabelaBadge = log.tabela === 'celulares' ? 'primary' : 'info';
                        
                        html += `
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="heading${index}">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse${index}">
                                    <div class="d-flex w-100 justify-content-between align-items-center pe-3">
                                        <div>
                                            <span class="badge bg-${acaoBadge} me-2">${log.acao.toUpperCase()}</span>
                                            <span class="badge bg-${tabelaBadge} me-2">${log.tabela}</span>
                                            <strong>${log.usuario_nome}</strong>
                                            ${log.registro_id ? `<span class="text-muted">- ID: ${log.registro_id}</span>` : ''}
                                        </div>
                                        <small class="text-muted">${data}</small>
                                    </div>
                                </button>
                            </h2>
                            <div id="collapse${index}" class="accordion-collapse collapse" data-bs-parent="#accordionAuditoria">
                                <div class="accordion-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <h6 class="text-muted">Informações</h6>
                                            <ul class="list-unstyled">
                                                <li><strong>Usuário:</strong> ${log.usuario_nome} ${log.usuario_id ? `(ID: ${log.usuario_id})` : ''}</li>
                                                <li><strong>Ação:</strong> ${log.acao}</li>
                                                <li><strong>Tabela:</strong> ${log.tabela}</li>
                                                <li><strong>Registro ID:</strong> ${log.registro_id || 'N/A'}</li>
                                                <li><strong>Data/Hora:</strong> ${data}</li>
                                                <li><strong>IP:</strong> ${log.ip_address || 'N/A'}</li>
                                            </ul>
                                        </div>
                                        <div class="col-md-6">
                                            <h6 class="text-muted">User Agent</h6>
                                            <p class="small text-muted">${log.user_agent || 'N/A'}</p>
                                        </div>
                                    </div>
                                    
                                    ${log.dados_anteriores ? `
                                    <div class="mt-3">
                                        <h6 class="text-danger">Dados Anteriores</h6>
                                        <pre class="bg-light p-3 rounded" style="max-height: 300px; overflow-y: auto;"><code>${JSON.stringify(JSON.parse(log.dados_anteriores), null, 2)}</code></pre>
                                    </div>
                                    ` : ''}
                                    
                                    ${log.dados_novos ? `
                                    <div class="mt-3">
                                        <h6 class="text-success">Dados Novos</h6>
                                        <pre class="bg-light p-3 rounded" style="max-height: 300px; overflow-y: auto;"><code>${JSON.stringify(JSON.parse(log.dados_novos), null, 2)}</code></pre>
                                    </div>
                                    ` : ''}
                                </div>
                            </div>
                        </div>`;
                    });
                    
                    html += '</div>';
                    conteudo.innerHTML = html;
                } else {
                    conteudo.innerHTML = '<div class="alert alert-info">Nenhum log de auditoria encontrado.</div>';
                }
            })
            .catch(err => {
                loading.style.display = 'none';
                conteudo.innerHTML = '<div class="alert alert-danger">Erro ao carregar logs de auditoria.</div>';
                console.error('Erro:', err);
            });
    }
    
    // Carregar auditoria ao abrir modal
    modalAuditoria.addEventListener('shown.bs.modal', function() {
        carregarAuditoria();
    });
    
    // Botão filtrar
    btnFiltrar.addEventListener('click', function() {
        carregarAuditoria();
    });
    
    // Botão limpar filtros
    btnLimpar.addEventListener('click', function() {
        document.getElementById('filtro_tabela').value = '';
        document.getElementById('filtro_acao').value = '';
        document.getElementById('filtro_limite').value = '50';
        carregarAuditoria();
    });
})();
</script>
</body>
</html>
