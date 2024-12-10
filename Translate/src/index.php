<?php

// Função para listar diretórios de idiomas
function listarLinguagens($path) {
    return array_filter(scandir($path), fn($item) => $item[0] !== '.' && is_dir("$path/$item"));
}

// Diretório de idiomas
$diretorioIdiomas = './langs';
$linguagens = listarLinguagens($diretorioIdiomas);
?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Seleção de Idiomas</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body>
        <div class="container mt-5">
            <h1 class="text-center mb-4">Selecione os Idiomas</h1>
            <form id="languageForm" method="POST" action="langs.php">
                <div class="row">
                    <?php foreach ($linguagens as $linguagem): ?>
                        <div class="col-6 col-md-4 col-lg-2 mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="languages[]" value="<?= $linguagem ?>" id="<?= $linguagem ?>">
                                <label class="form-check-label" for="<?= $linguagem ?>"><?= $linguagem ?></label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="text-center mt-4">
                    <button type="submit" class="btn btn-primary">Start</button>
                </div>
            </form>

        </div>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
        <script>
       // JavaScript para redirecionamento dinâmico
        document.getElementById('languageForm').addEventListener('submit', function (e) {
            const form = e.target;
            const formData = new FormData(form);
            
            // Extrair os idiomas selecionados
            const selectedLanguages = Array.from(formData.getAll('languages[]'));

             // Criar uma string delimitada por ";" para os idiomas selecionados
            const langsString = selectedLanguages.join(';');

            // Redirecionar para o langs.php dinamicamente
            const actionUrl = form.action + '?' + new URLSearchParams({ languages: langsString });
            form.action = actionUrl; // Atualizar a ação do formulário
        });
        </script>
    </body>
</html>
