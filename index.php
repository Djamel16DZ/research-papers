<?php
// =========================================================================
// 1. CONFIGURATION DE L'ENVIRONNEMENT ET DE LA BASE DE DONNÉES
// =========================================================================
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$db_host = '127.0.0.1';
$db_name = 'research_papers_db';
$db_user = 'root';
$db_pass = 'root';

$db_error = null;
$pdo = null;

// Dossier de stockage des fichiers PDF
$uploadDir = __DIR__ . '/uploads/pdfs/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Classification algérienne des revues scientifiques (DGRSDT) avec champs de référence
$classificationTypes = [
    'cat_a_plus' => [
        'label'  => 'Catégorie A+', 
        'icon'   => 'fa-award', 
        'fields' => [
            'authors'      => 'Auteurs', 
            'journal'      => 'Nom de la revue', 
            'year'         => 'Année', 
            'volume_issue' => 'Vol. / N°', 
            'pages'        => 'Pages', 
            'doi'          => 'DOI / Lien'
        ]
    ],
    'cat_a' => [
        'label'  => 'Catégorie A', 
        'icon'   => 'fa-star', 
        'fields' => [
            'authors'      => 'Auteurs', 
            'journal'      => 'Nom de la revue', 
            'year'         => 'Année', 
            'volume_issue' => 'Vol. / N°', 
            'pages'        => 'Pages', 
            'doi'          => 'DOI / Lien'
        ]
    ],
    'cat_b' => [
        'label'  => 'Catégorie B', 
        'icon'   => 'fa-bookmark', 
        'fields' => [
            'authors'      => 'Auteurs', 
            'journal'      => 'Nom de la revue', 
            'year'         => 'Année', 
            'volume_issue' => 'Vol. / N°', 
            'pages'        => 'Pages', 
            'doi'          => 'DOI / Lien'
        ]
    ],
    'cat_c' => [
        'label'  => 'Catégorie C', 
        'icon'   => 'fa-book-open', 
        'fields' => [
            'authors'      => 'Auteurs', 
            'journal'      => 'Nom de la revue', 
            'year'         => 'Année', 
            'volume_issue' => 'Vol. / N°', 
            'pages'        => 'Pages', 
            'doi'          => 'DOI / Lien'
        ]
    ]
];

if (!extension_loaded('pdo_mysql')) {
    $db_error = "L'extension 'pdo_mysql' est désactivée ou manquante dans votre configuration PHP (php.ini).";
} else {
    try {
        $pdo = new \PDO("mysql:host={$db_host};charset=utf8mb4", $db_user, $db_pass, [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db_name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `{$db_name}`");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `research_items` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `classification` VARCHAR(50) NOT NULL,
            `title` VARCHAR(255) NOT NULL,
            `pdf_file` VARCHAR(255) DEFAULT NULL,
            `meta_data` JSON DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_classification` (`classification`),
            FULLTEXT INDEX `ft_paper_search` (`title`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    } catch (\PDOException $e) {
        $db_error =$e->getMessage();
    }
}

// =========================================================================
// 2. REQUÊTE POST : ENREGISTRER (AJOUTER OU MODIFIER) UN ARTICLE
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) &&$_POST['action'] === 'save_item') {
    header('Content-Type: application/json');
    if ($pdo === null) {
        echo json_encode(['success' => false, 'error' => $db_error ?? 'Base de données non connectée']);
        exit;
    }

    try {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $classification = trim($_POST['classification']);
        
        if (!array_key_exists($classification,$classificationTypes)) {
            echo json_encode(['success' => false, 'error' => 'Classification invalide.']);
            exit;
        }

        $title = trim($_POST['title']);

        // Récupération dynamique des métadonnées bibliographiques
        $meta = [];
        if (isset($classificationTypes[$classification]['fields'])) {
            foreach ($classificationTypes[$classification]['fields'] as $key =>$label) {
                $meta[$key] = isset($_POST['meta_' .$key]) ? trim($_POST['meta_' .$key]) : '';
            }
        }
        $meta_json = json_encode($meta, JSON_UNESCAPED_UNICODE);

        // Gestion du fichier PDF
        $pdf_filename = null;
        if ($id > 0) {
            $stmtOld =$pdo->prepare("SELECT pdf_file FROM research_items WHERE id = :id");
            $stmtOld->execute([':id' =>$id]);
            $oldData =$stmtOld->fetch();
            $pdf_filename =$oldData['pdf_file'] ?? null;
        }

        if (isset($_FILES['pdf_file']) &&$_FILES['pdf_file']['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath =$_FILES['pdf_file']['tmp_name'];
            $fileName =$_FILES['pdf_file']['name'];
            if (strtolower(pathinfo($fileName, PATHINFO_EXTENSION)) === 'pdf') {$newFileName = md5(time() . $fileName) . '.pdf';$destPath = $uploadDir .$newFileName;
                if (move_uploaded_file($fileTmpPath,$destPath)) {
                    if (!empty($pdf_filename) && file_exists($uploadDir .$pdf_filename)) {
                        @unlink($uploadDir .$pdf_filename);
                    }
                    $pdf_filename =$newFileName;
                }
            }
        }

        if ($id > 0) {
            $stmt =$pdo->prepare("UPDATE research_items SET classification = :classification, title = :title, pdf_file = :pdf_file, meta_data = :meta_data WHERE id = :id");
            $stmt->execute([
                ':classification' => $classification,
                ':title'          => $title,
                ':pdf_file'       => $pdf_filename,
                ':meta_data'      => $meta_json,
                ':id'             => $id
            ]);
        } else {
            $stmt =$pdo->prepare("INSERT INTO research_items (classification, title, pdf_file, meta_data) VALUES (:classification, :title, :pdf_file, :meta_data)");
            $stmt->execute([
                ':classification' => $classification,
                ':title'          => $title,
                ':pdf_file'       => $pdf_filename,
                ':meta_data'      => $meta_json,
            ]);
        }

        echo json_encode(['success' => true]);
    } catch (\PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// =========================================================================
// 3. REQUÊTE POST : SUPPRIMER UN ARTICLE
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) &&$_POST['action'] === 'delete_item') {
    header('Content-Type: application/json');
    if ($pdo === null) {
        echo json_encode(['success' => false, 'error' => $db_error ?? 'Base de données non connectée']);
        exit;
    }

    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if ($id <= 0) {
        echo json_encode(['success' => false, 'error' => 'ID invalide.']);
        exit;
    }

    try {
        $stmtOld =$pdo->prepare("SELECT pdf_file FROM research_items WHERE id = :id");
        $stmtOld->execute([':id' =>$id]);
        $oldData =$stmtOld->fetch();
        if (!empty($oldData['pdf_file']) && file_exists($uploadDir .$oldData['pdf_file'])) {
            @unlink($uploadDir .$oldData['pdf_file']);
        }

        $stmt =$pdo->prepare("DELETE FROM research_items WHERE id = :id");
        $stmt->execute([':id' =>$id]);

        echo json_encode(['success' => true]);
    } catch (\PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// =========================================================================
// 4. STATISTIQUES GLOBALES
// =========================================================================
$stats = [
    'total_items' => 0,
    'by_classification' => []
];

if ($pdo !== null) {
    try {
        $stats['total_items'] = (int)$pdo->query("SELECT COUNT(*) FROM research_items")->fetchColumn();
        $classStmt =$pdo->query("SELECT classification, COUNT(*) as count FROM research_items GROUP BY classification");
        while ($row = $classStmt->fetch()) {$stats['by_classification'][$row['classification']] = (int)$row['count'];
        }
    } catch (\PDOException $e) {$db_error = "Erreur des métriques : " . $e->getMessage();
    }
}

// =========================================================================
// 5. RECHERCHE ET FILTRAGE
// =========================================================================
$current_view = isset($_GET['view']) ? trim($_GET['view']) : 'dashboard';
$search       = isset($_GET['q']) ? trim($_GET['q']) : '';$page         = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit        = 30;
$offset       = ($page - 1) *$limit;

$whereClauses = [];$params       = [];

if ($current_view !== 'dashboard' && array_key_exists($current_view, $classificationTypes)) {$whereClauses[] = "classification = :classification";
    $params[':classification'] =$current_view;
}

if (!empty($search)) {$whereClauses[] = "(title LIKE :s1 OR meta_data LIKE :s2)";
    $params[':s1'] = '%' .$search . '%';
    $params[':s2'] = '%' .$search . '%';
}

$whereSql = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

$filteredRecordsCount = 0;
$records = [];

if ($pdo !== null) {
    try {
        $countStmt =$pdo->prepare("SELECT COUNT(*) FROM research_items {$whereSql}");
        foreach ($params as$key => $val) {$countStmt->bindValue($key,$val, \PDO::PARAM_STR);
        }
        $countStmt->execute();
        $filteredRecordsCount = (int)$countStmt->fetchColumn();

        $sql = "SELECT * FROM research_items {$whereSql} ORDER BY id DESC LIMIT :limit OFFSET :offset";
        $stmt = $pdo->prepare($sql);
        foreach ($params as$key => $val) {$stmt->bindValue($key,$val, \PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', (int)$limit, \PDO::PARAM_INT);$stmt->bindValue(':offset', (int)$offset, \PDO::PARAM_INT);$stmt->execute();
        $records =$stmt->fetchAll();
    } catch (\PDOException $e) {$db_error = "Erreur de requête : " . $e->getMessage();
    }
}

$totalPages = max(1, ceil($filteredRecordsCount / $limit));

if (isset($_GET['ajax']) &&$_GET['ajax'] === '1') {
    header('Content-Type: application/json');
    echo json_encode([
        'totalRecords' => $filteredRecordsCount,
        'page'         => $page,
        'totalPages'   => $totalPages,
        'records'      => $records,
        'db_error'     => $db_error
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr" class="h-full bg-zinc-950 text-zinc-100 font-sans antialiased">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Research Papers Library</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #09090b; }
        ::-webkit-scrollbar-thumb { background: #27272a; border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: #3f3f46; }
        #sidebar { transition: margin-left 0.25s ease-in-out; }
        #sidebar.collapsed { margin-left: -15rem; }
    </style>
</head>
<body class="h-full flex overflow-hidden text-xs select-none">

    <!-- MENU LATÉRAL -->
    <aside id="sidebar" class="w-60 bg-zinc-900 border-r border-zinc-800 flex flex-col shrink-0 z-20">
        <div class="h-12 border-b border-zinc-800 flex items-center px-4 space-x-2.5">
            <div class="w-6 h-6 rounded bg-purple-600 flex items-center justify-center text-white font-bold text-xs">
                <i class="fa-solid fa-graduation-cap"></i>
            </div>
            <span class="font-bold text-white tracking-tight text-sm">Research<span class="text-purple-400">Papers</span></span>
            <span class="ml-auto text-[10px] font-mono px-1.5 py-0.5 rounded bg-zinc-800 text-zinc-400 border border-zinc-700/50">DZ</span>
        </div>

        <div class="flex-1 overflow-y-auto p-2.5 space-y-4">
            <div>
                <div class="px-2 text-[10px] font-bold uppercase tracking-wider text-zinc-500 mb-1">Navigation</div>
                <nav class="space-y-0.5">
                    <a href="?view=dashboard" class="flex items-center justify-between px-2.5 py-1.5 rounded-md <?= $current_view === 'dashboard' ? 'bg-purple-600/10 text-purple-400 font-semibold border border-purple-500/20' : 'text-zinc-400 hover:bg-zinc-800 hover:text-zinc-200' ?>">
                        <div class="flex items-center space-x-2.5">
                            <i class="fa-solid fa-chart-pie w-4 text-center"></i>
                            <span>Tableau de bord</span>
                        </div>
                    </a>
                </nav>
            </div>

            <div>
                <div class="px-2 text-[10px] font-bold uppercase tracking-wider text-zinc-500 mb-1">Classification DGRSDT</div>
                <nav class="space-y-0.5">
                    <?php foreach ($classificationTypes as $key =>$type): ?>
                        <a href="?view=<?= $key ?>" class="flex items-center justify-between px-2.5 py-1.5 rounded-md <?= $current_view ===$key ? 'bg-purple-600/10 text-purple-400 font-semibold border border-purple-500/20' : 'text-zinc-400 hover:bg-zinc-800 hover:text-zinc-200' ?>">
                            <div class="flex items-center space-x-2.5">
                                <i class="fa-solid <?= $type['icon'] ?> w-4 text-center"></i>
                                <span><?= $type['label'] ?></span>
                            </div>
                            <span class="font-mono text-[10px] px-1.5 py-0.2 rounded bg-zinc-800 text-zinc-400">
                                <?= $stats['by_classification'][$key] ?? 0 ?>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </nav>
            </div>
        </div>
    </aside>

    <!-- ZONE PRINCIPALE -->
    <main class="flex-1 flex flex-col min-w-0 bg-zinc-950">
        <header class="h-12 border-b border-zinc-800 bg-zinc-900/60 px-4 flex items-center justify-between shrink-0 space-x-3">
            <div class="flex items-center space-x-3 flex-1 max-w-2xl">
                <button onclick="toggleSidebar()" class="p-1.5 text-zinc-400 hover:text-white hover:bg-zinc-800 rounded-md border border-zinc-800 transition shrink-0">
                    <i class="fa-solid fa-bars-staggered text-xs"></i>
                </button>

                <?php if ($current_view !== 'dashboard'): ?>
                <div class="relative flex-1">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-2.5 text-zinc-500 text-xs"></i>
                    <input type="text" id="searchInput" value="<?= htmlspecialchars($search) ?>" placeholder="Rechercher par titre, auteur, revue..." 
                           oninput="triggerLiveSearch()"
                           class="w-full bg-zinc-950 border border-zinc-800 rounded-md pl-8 pr-3 py-1.5 text-xs text-zinc-200 placeholder-zinc-500 focus:outline-none focus:border-purple-500 transition">
                </div>
                <?php else: ?>
                    <span class="font-bold text-zinc-300 text-xs">Tableau de bord de la Bibliothèque</span>
                <?php endif; ?>
            </div>

            <div class="flex items-center space-x-2">
                <button onclick="openItemModal()" class="px-2.5 py-1.5 bg-purple-600 hover:bg-purple-500 text-white rounded-md font-semibold flex items-center space-x-1.5 transition">
                    <i class="fa-solid fa-plus text-xs"></i>
                    <span>Ajouter un article</span>
                </button>
            </div>
        </header>

        <div class="flex-1 flex overflow-hidden relative">

            <?php if ($current_view === 'dashboard'): ?>
            <!-- DASHBOARD -->
            <div class="flex-1 overflow-y-auto p-6 space-y-6 bg-zinc-950">
                <h1 class="text-base font-bold text-white tracking-tight">Vue d'ensemble de la Bibliothèque</h1>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="bg-zinc-900/85 border border-zinc-800 rounded-lg p-4 flex flex-col justify-between">
                        <div class="flex items-center justify-between text-zinc-400 mb-2">
                            <span>Total Articles Indexés</span>
                            <i class="fa-solid fa-book text-purple-400"></i>
                        </div>
                        <div class="text-2xl font-bold font-mono text-white"><?= number_format($stats['total_items']) ?></div>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <!-- CATALOGUE -->
            <div id="view-catalog" class="flex-1 flex flex-col min-w-0">
                <div class="flex-1 overflow-auto">
                    <table class="w-full text-left border-collapse font-sans">
                        <thead class="bg-zinc-900/90 sticky top-0 border-b border-zinc-800 backdrop-blur z-10 text-[11px] font-semibold text-zinc-400">
                            <tr>
                                <th class="py-2 px-3">Titre de l'article</th>
                                <th class="py-2 px-3 w-28">PDF</th>
                                <?php foreach ($classificationTypes[$current_view]['fields'] as $fKey =>$fLabel): ?>
                                    <th class="py-2 px-3"><?= $fLabel ?></th>
                                <?php endforeach; ?>
                                <th class="py-2 px-3 w-20 text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="catalogTableBody" class="divide-y divide-zinc-800/60 font-normal text-zinc-300">
                            <?php if (empty($records)): ?>
                            <tr>
                                <td colspan="10" class="py-8 text-center text-zinc-500 italic">Aucun article trouvé.</td>
                            </tr>
                            <?php else: foreach ($records as$row): 
                                $meta = json_decode($row['meta_data'] ?? '{}', true);
                            ?>
                            <tr class="hover:bg-zinc-900/60 transition group" id="row-<?= $row['id'] ?>">
                                <td class="py-2 px-3 font-semibold text-zinc-100 group-hover:text-purple-400 transition"><?= htmlspecialchars($row['title']) ?></td>
                                <td class="py-2 px-3">
                                    <?php if (!empty($row['pdf_file'])): ?>
                                        <a href="uploads/pdfs/<?= htmlspecialchars($row['pdf_file']) ?>" target="_blank" class="text-purple-400 hover:text-purple-300 flex items-center space-x-1 font-mono text-[11px]">
                                            <i class="fa-solid fa-file-pdf text-rose-400"></i>
                                            <span>Ouvrir PDF</span>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-zinc-600 italic">Aucun PDF</span>
                                    <?php endif; ?>
                                </td>
                                
                                <?php foreach ($classificationTypes[$current_view]['fields'] as $fKey =>$fLabel): ?>
                                    <td class="py-2 px-3 text-zinc-400 text-[11px]"><?= htmlspecialchars($meta[$fKey] ?? 'N/A') ?></td>
                                <?php endforeach; ?>

                                <td class="py-2 px-3 text-center space-x-1">
                                    <button onclick='editItem(<?= json_encode($row) ?>)' class="p-1 text-zinc-500 hover:text-amber-400 transition" title="Modifier">
                                        <i class="fa-solid fa-pen-to-square text-xs"></i>
                                    </button>
                                    <button onclick="deleteItem(<?= $row['id'] ?>)" class="p-1 text-zinc-500 hover:text-rose-400 transition" title="Supprimer">
                                        <i class="fa-solid fa-trash-can text-xs"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </main>

    <!-- MODALE -->
    <div id="itemModal" class="hidden fixed inset-0 bg-black/70 backdrop-blur-sm flex items-center justify-center z-50 p-4">
        <div class="bg-zinc-900 border border-zinc-800 w-full max-w-lg rounded-lg shadow-2xl flex flex-col overflow-hidden max-h-[90vh]">
            <div class="px-4 py-3 border-b border-zinc-800 flex justify-between items-center bg-zinc-900/80">
                <span class="font-bold text-white text-xs flex items-center space-x-2">
                    <i class="fa-solid fa-plus-circle text-purple-400"></i>
                    <span id="modalTitle">Ajouter un article</span>
                </span>
                <button onclick="closeItemModal()" class="text-zinc-500 hover:text-white transition"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <form id="addItemForm" onsubmit="saveItem(event)" enctype="multipart/form-data" class="p-4 space-y-3 overflow-y-auto">
                <input type="hidden" name="id" id="formId" value="">
                
                <div>
                    <label class="block text-zinc-400 mb-1">Classification DGRSDT <span class="text-rose-400">*</span></label>
                    <select name="classification" id="formClassification" onchange="updateModalFields()" class="w-full bg-zinc-950 border border-zinc-800 rounded px-2.5 py-1.5 text-xs text-white focus:outline-none focus:border-purple-500">
                        <?php foreach ($classificationTypes as $k =>$t): ?>
                            <option value="<?= $k ?>" <?= $current_view === $k ? 'selected' : '' ?>><?= $t['label'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-zinc-400 mb-1">Titre de l'article <span class="text-rose-400">*</span></label>
                    <input type="text" name="title" id="formTitle" required placeholder="Titre complet de la publication..." class="w-full bg-zinc-950 border border-zinc-800 rounded px-2.5 py-1.5 text-xs text-white focus:outline-none focus:border-purple-500">
                </div>

                <div>
                    <label class="block text-zinc-400 mb-1">Fichier PDF</label>
                    <input type="file" name="pdf_file" id="formPdfFile" accept="application/pdf" class="w-full bg-zinc-950 border border-zinc-800 rounded px-2 py-1 text-[11px] text-zinc-400">
                </div>

                <div id="dynamicFieldsContainer" class="grid grid-cols-2 gap-3 pt-2 border-t border-zinc-800">
                    <!-- Généré dynamiquement -->
                </div>

                <div class="pt-2 border-t border-zinc-800 flex justify-end space-x-2">
                    <button type="button" onclick="closeItemModal()" class="px-3 py-1.5 bg-zinc-800 hover:bg-zinc-700 text-zinc-300 rounded font-semibold transition">Annuler</button>
                    <button type="submit" class="px-3 py-1.5 bg-purple-600 hover:bg-purple-500 text-white rounded font-semibold transition">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const classificationConfig = <?= json_encode($classificationTypes) ?>;

        function toggleSidebar() { document.getElementById('sidebar').classList.toggle('collapsed'); }

        function updateModalFields(existingMeta = {}) {
            const classSelect = document.getElementById('formClassification').value;
            const container = document.getElementById('dynamicFieldsContainer');
            const fields = classificationConfig[classSelect]?.fields || {};

            container.innerHTML = Object.entries(fields).map(([key, label]) => `
                <div>
                    <label class="block text-zinc-400 mb-1">${label}</label>
                    <input type="text" name="meta_${key}" value="${escapeHtml(existingMeta[key] || '')}" placeholder="${label}" class="w-full bg-zinc-950 border border-zinc-800 rounded px-2.5 py-1.5 text-xs text-white focus:outline-none focus:border-purple-500">
                </div>
            `).join('');
        }

        function openItemModal() {
            document.getElementById('formId').value = '';
            document.getElementById('addItemForm').reset();
            document.getElementById('modalTitle').innerText = 'Ajouter un article';
            updateModalFields();
            document.getElementById('itemModal').classList.remove('hidden');
        }

        function editItem(row) {
            document.getElementById('formId').value = row.id;
            document.getElementById('formClassification').value = row.classification;
            document.getElementById('formTitle').value = row.title;
            
            let meta = {};
            try { meta = JSON.parse(row.meta_data || '{}'); } catch(e){}
            updateModalFields(meta);

            document.getElementById('modalTitle').innerText = 'Modifier l’article';
            document.getElementById('itemModal').classList.remove('hidden');
        }

        function closeItemModal() { document.getElementById('itemModal').classList.add('hidden'); }

        function triggerLiveSearch() {
            setTimeout(() => {
                const q = document.getElementById('searchInput')?.value || '';
                const currentView = '<?= $current_view ?>';
                fetch(`index.php?view=${currentView}&ajax=1&q=${encodeURIComponent(q)}`)
                    .then(res => res.json())
                    .then(data => { renderTableRows(data.records); });
            }, 200);
        }

        function renderTableRows(records) {
            const tbody = document.getElementById('catalogTableBody');
            const currentView = '<?= $current_view ?>';
            const fields = classificationConfig[currentView]?.fields || {};

            if (!records || records.length === 0) {
                tbody.innerHTML = `<tr><td colspan="10" class="py-8 text-center text-zinc-500 italic">Aucun article trouvé.</td></tr>`;
                return;
            }

            tbody.innerHTML = records.map(row => {
                let meta = {};
                try { meta = JSON.parse(row.meta_data || '{}'); } catch(e){}
                let metaColumns = Object.keys(fields).map(fKey => `<td class="py-2 px-3 text-zinc-400 text-[11px]">${escapeHtml(meta[fKey] || 'N/A')}</td>`).join('');
                let pdfColumn = row.pdf_file ? `<a href="uploads/pdfs/${escapeHtml(row.pdf_file)}" target="_blank" class="text-purple-400 hover:text-purple-300 font-mono text-[11px]"><i class="fa-solid fa-file-pdf text-rose-400"></i> PDF</a>` : `<span class="text-zinc-600 italic">Aucun</span>`;

                return `
                    <tr class="hover:bg-zinc-900/60 transition group">
                        <td class="py-2 px-3 font-semibold text-zinc-100 group-hover:text-purple-400">${escapeHtml(row.title)}</td>
                        <td class="py-2 px-3">${pdfColumn}</td>
                        ${metaColumns}
                        <td class="py-2 px-3 text-center space-x-1">
                            <button onclick='editItem(${JSON.stringify(row)})' class="p-1 text-zinc-500 hover:text-amber-400"><i class="fa-solid fa-pen-to-square"></i></button>
                            <button onclick="deleteItem(${row.id})" class="p-1 text-zinc-500 hover:text-rose-400"><i class="fa-solid fa-trash-can"></i></button>
                        </td>
                    </tr>
                `;
            }).join('');
        }

        function saveItem(e) {
            e.preventDefault();
            const formData = new FormData(document.getElementById('addItemForm'));
            formData.append('action', 'save_item');
            fetch('index.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(res => { if (res.success) location.reload(); else alert(res.error); });
        }

        function deleteItem(id) {
            if (!confirm("Supprimer cet article ?")) return;
            const formData = new FormData();
            formData.append('action', 'delete_item');
            formData.append('id', id);
            fetch('index.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(res => { if (res.success) location.reload(); else alert(res.error); });
        }

        function escapeHtml(str) {
            return String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        updateModalFields();
    </script>
</body>
</html>