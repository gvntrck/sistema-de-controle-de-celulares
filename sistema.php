<?php
/**
 * Arquivo único: /celulares-admin.php
 * Coloque na raiz do WordPress. Requer wp-load.php.
 * MVP: lista celulares + metadados + dados do colaborador.
 * Version: 1.7.0
 */

declare(strict_types=1);

// --- Bootstrap WordPress ---
require_once __DIR__ . '/wp-load.php';

/** @var wpdb $wpdb */
global $wpdb;

// --- Configurações básicas ---
$prefix = $wpdb->prefix;
$tables = (object) [
    'celulares'          => $prefix . 'celulares',
    'celulares_meta'     => $prefix . 'celulares_meta',
    'colaboradores'      => $prefix . 'colaboradores',
    'colaboradores_meta' => $prefix . 'colaboradores_meta',
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
        status VARCHAR(50) NOT NULL DEFAULT 'disponivel', -- ex: disponivel, emprestado, manutencao, inativo, defeito
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

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    foreach ($sql as $q) {
        $wpdb->query($q); // idempotente com IF NOT EXISTS
    }

    // Garante chaves importantes de meta iniciais no comentário abaixo.
    // celulares_meta padrão: imei, serial number
    // colaboradores_meta padrão: setor, local
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
    
    $wpdb->insert($tables->colaboradores, [
        'nome' => sanitize_text_field($input['colaborador_nome']),
        'sobrenome' => sanitize_text_field($input['colaborador_sobrenome']),
        'matricula' => sanitize_text_field($input['colaborador_matricula'])
    ]);
    
    $colaborador_id = (int) $wpdb->insert_id;
    
    // Salvar metas do colaborador
    if (!empty($input['colaborador_setor'])) {
        $wpdb->insert($tables->colaboradores_meta, [
            'colaborador_id' => $colaborador_id,
            'meta_key' => 'setor',
            'meta_value' => sanitize_text_field($input['colaborador_setor'])
        ]);
    }
    
    if (!empty($input['colaborador_local'])) {
        $wpdb->insert($tables->colaboradores_meta, [
            'colaborador_id' => $colaborador_id,
            'meta_key' => 'local',
            'meta_value' => sanitize_text_field($input['colaborador_local'])
        ]);
    }
    
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
        
        // Inserir celular
        $wpdb->insert($tables->celulares, [
            'marca' => sanitize_text_field($input['marca']),
            'modelo' => sanitize_text_field($input['modelo']),
            'colaborador' => $colaborador_id,
            'status' => sanitize_text_field($input['status'])
        ]);
        $celular_id = (int) $wpdb->insert_id;
        
        // Salvar metas do celular
        salvar_metas_celular($celular_id, $input);
        
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
        
        // Determinar colaborador (novo ou existente)
        $colaborador_id = !empty($input['novo_colaborador']) 
            ? criar_colaborador($input) 
            : (!empty($input['colaborador_id']) ? (int) $input['colaborador_id'] : null);
        
        // Atualizar dados principais do celular
        $wpdb->update(
            $tables->celulares,
            [
                'marca' => sanitize_text_field($input['marca']),
                'modelo' => sanitize_text_field($input['modelo']),
                'colaborador' => $colaborador_id,
                'status' => sanitize_text_field($input['status'])
            ],
            ['id' => $celular_id]
        );
        
        // Atualizar ou inserir metas
        upsert_metas_celular($celular_id, $input);
        
        echo json_encode(['success' => true, 'celular_id' => $celular_id]);
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
        body { padding: 24px; }
        .table thead th { white-space: nowrap; }
        .status-badge { text-transform: capitalize; }
        .search-input { max-width: 360px; }
        .chip {
            display:inline-block; padding:.25rem .5rem; border-radius:999px; background:#f1f3f5; font-size:.8rem;
        }
        .muted { color:#6c757d; }
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
    
    <header class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h4 m-0">Controle de Celulares</h1>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalAdicionarCelular">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-plus-lg" viewBox="0 0 16 16">
                    <path fill-rule="evenodd" d="M8 2a.5.5 0 0 1 .5.5v5h5a.5.5 0 0 1 0 1h-5v5a.5.5 0 0 1-1 0v-5h-5a.5.5 0 0 1 0-1h5v-5A.5.5 0 0 1 8 2"/>
                </svg>
                Adicionar Celular
            </button>
            <input id="search" type="search" class="form-control form-control-sm search-input" placeholder="Pesquisar...">
            <span class="chip">Tabelas: <?php echo esc($tables->celulares); ?>, <?php echo esc($tables->celulares_meta); ?>, <?php echo esc($tables->colaboradores); ?>, <?php echo esc($tables->colaboradores_meta); ?></span>
        </div>
    </header>

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
                            <button class="btn btn-sm btn-outline-primary btn-editar" data-id="<?php echo esc($r['id']); ?>" title="Editar">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
                                    <path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168l10-10zM11.207 2.5 13.5 4.793 14.793 3.5 12.5 1.207 11.207 2.5zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.293l6.5-6.5zm-9.761 5.175-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325z"/>
                                </svg>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="11" class="text-center text-muted">Nenhum registro.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
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
                bootbox.alert({
                    message: isEdit ? 'Celular atualizado com sucesso!' : 'Celular adicionado com sucesso!',
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
</script>
</body>
</html>
