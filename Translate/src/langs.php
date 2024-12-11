<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Configurar cabeçalhos para evitar cache e forçar atualização em tempo real
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
while (ob_get_level()) {
    ob_end_clean();
}
ob_implicit_flush(true);
// Iniciar processamento
echo "<pre>";
echo "Starting SQL init...\n";

$pdo = new PDO("mysql:host=localhost;dbname=database", "root", "Passwd");


// Criar a função base10to36 se não existir
$pdo->exec("
    CREATE FUNCTION IF NOT EXISTS `base10to36`(num BIGINT) RETURNS varchar(255) CHARSET utf8mb4
    DETERMINISTIC
    BEGIN
        DECLARE chars CHAR(36) DEFAULT '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        DECLARE stream CHAR(32) DEFAULT '';
        DECLARE res INT;
        WHILE num > 0 DO
            SET res = num % 36;
            SET num = num DIV 36;
            SET stream = CONCAT(MID(chars, res + 1, 1), stream);
        END WHILE;
        RETURN stream;
    END
");

// Drop e recriação das tabelas
$pdo->exec("
    DROP TABLE IF EXISTS `a_languages`, `a_langs`, `a_lang_key_names`, `a_modules`;
    CREATE TABLE `a_languages` (
        `id` int unsigned NOT NULL,
        `language` char(5) DEFAULT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

    CREATE TABLE `a_langs` (
        `id` int NOT NULL AUTO_INCREMENT,
        `id36` char(6) DEFAULT NULL,
        `lang_code` int NOT NULL,
        `key_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
        `module` varchar(255) DEFAULT NULL,
        `translation` text NOT NULL,
        `module_id` int DEFAULT NULL,
        `key_name_id` int DEFAULT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

    CREATE TABLE `a_lang_key_names` (
        `id` int NOT NULL AUTO_INCREMENT,
        `key_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
        `key_36` char(4) DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `key_name_UNIQUE` (`key_name`)
    ) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
    
CREATE TABLE `a_modules` (
  `id` int NOT NULL AUTO_INCREMENT,
  `module` varchar(80) DEFAULT NULL,
  `key_36` char(2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `module_UNIQUE` (`module`)
) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

");

echo "Starting language processing...\n";

// Obter idiomas selecionados
$selectedLanguages = isset($_POST['languages']) ? $_POST['languages'] : [];

// Certificar-se de que 'en_US' está incluído
if (!in_array('en_US', $selectedLanguages)) {
    array_unshift($selectedLanguages, 'en_US');
}

// Inserir idiomas na tabela `a_languages`
$pdo->exec("TRUNCATE TABLE a_languages");
$langId = 0;
foreach ($selectedLanguages as $lang) {
    $stmt = $pdo->prepare("INSERT INTO a_languages (id, language) VALUES (?, ?)");
    $stmt->execute([$langId++, $lang]);
}

// Processar arquivos de tradução
// Preparar a inserção na tabela `a_langs`
$stmt = $pdo->prepare("
    INSERT INTO a_langs (lang_code, key_name, module, translation)
    VALUES (:lang_code, :key_name, :module, :translation)
");

foreach ($selectedLanguages as $langId => $langCode) {
    echo "Processing language: $langCode<br>\n";
    $langPath = "./langs/$langCode";
    $files = array_diff(scandir($langPath), ['.', '..']);

    foreach ($files as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'lang') {
            $moduleName = strtok($file, '.');
            $filePath = "$langPath/$file";
            echo "  Processing file: $file\n";

            $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

            foreach ($lines as $line) {
                if (strpos($line, '#') === 0 || strpos($line, '=') === false) {
                    continue; // Ignorar cabeçalhos e linhas inválidas
                }

                [$keyName, $translation] = array_map('trim', explode('=', $line, 2));
                $stmt->execute([
                    ':lang_code' => $langId,
                    ':key_name' => $keyName,
                    ':module' => $moduleName,
                    ':translation' => $translation
                ]);
            }
        }
    }
}

echo "Processing final SQL <br>\n";


// Pós-processamento
$pdo->exec("INSERT IGNORE INTO a_lang_key_names (key_name) SELECT DISTINCT BINARY key_name FROM a_langs");
$pdo->exec("INSERT IGNORE INTO a_modules (module) SELECT DISTINCT module FROM a_langs WHERE module IS NOT NULL");
$pdo->exec("UPDATE a_modules SET key_36 = LPAD(base10to36(id), 2, '0')");
$pdo->exec("UPDATE a_lang_key_names SET key_36 = LPAD(base10to36(id), 4, '0')");
$pdo->exec("UPDATE a_langs l
    JOIN a_modules m ON l.module = m.module
    SET l.module_id = m.id");
$pdo->exec("UPDATE a_langs l
    JOIN a_lang_key_names kn ON l.key_name = kn.key_name
    SET l.key_name_id = kn.id");
$pdo->exec("UPDATE a_langs l
    JOIN a_lang_key_names kn ON l.key_name_id = kn.id
    JOIN a_modules m ON l.module_id = m.id
    SET l.id36 = CONCAT(kn.key_36, m.key_36)");

echo "Create a_labels <br>\n";

$pdo->exec("CREATE TABLE `a_labels` (
  `id36` CHAR(6) NOT NULL,  `lang_code` INT NOT NULL,
  `module_id` INT DEFAULT NULL,  `key_name_id` INT DEFAULT NULL,
  `translation` TEXT NOT NULL,  PRIMARY KEY (`id36`, `lang_code`)) CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci");

$pdo->exec("INSERT ignore INTO a_labels (id36, lang_code, module_id, key_name_id, translation) SELECT id36, lang_code, module_id, key_name_id, translation FROM a_langs;");

echo "Language processing completed!<br>\n";
echo "</pre>";
flush();
?>
