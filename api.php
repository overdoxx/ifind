<?php
header('Content-Type: text/html; charset=utf-8');
require 'get_bearer.php';

// Carrega as configurações do arquivo JSON
$config = file_get_contents('config.json');
$config = json_decode($config, true);

// Verifica se o parâmetro 'consulta' foi recebido
if (!isset($_GET['consulta'])) {
    error_log('Consulta de CPF: Parâmetro "consulta" não recebido.');
    exit;
}

// Obtém e limpa o CPF
$cpf = trim($_GET['consulta']);
$cpf = preg_replace("/[^0-9]/", "", $cpf); // Remove caracteres não numéricos do CPF

// Verifica se o CPF está no formato correto
if (empty($cpf) || strlen($cpf) !== 11) {
    error_log('Consulta de CPF inválida: CPF não está no formato correto.');
    die('⚠️ Por favor, digite um CPF válido.');
}

// Obtém o token de acesso do arquivo de configuração
$bearer_token = $config['sipni_token'];

// Inicia a requisição CURL para consultar o CPF na API
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://servicos-cloud.saude.gov.br/pni-bff/v1/cidadao/cpf/'.$cpf);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
curl_setopt($ch, CURLOPT_ENCODING, "gzip");
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'User-Agent: Mozilla/5.0 (Windows NT ' . rand(11, 99) . '.0; Win64; x64) AppleWebKit/' . rand(111, 991) . '.' . rand(11, 99) . ' (KHTML, like Gecko) Chrome/' . rand(11, 99) . '.0.0.0 Safari/537.36',
    'Authorization: Bearer ' . $bearer_token,
    'DNT: 1',
    'Referer: https://si-pni.saude.gov.br/'
));
$response = curl_exec($ch);

// Verifica por erros na requisição CURL
if (curl_errno($ch)) {
    error_log('Erro na requisição CURL: ' . curl_error($ch));
    die('⚠️ Ocorreu um erro ao processar a requisição.');
}

// Obtém o código HTTP da resposta
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

// Fecha a conexão CURL
curl_close($ch);

// Verifica se houve erro na requisição HTTP
if ($http_code !== 200) {
    error_log('Erro na requisição HTTP: Código ' . $http_code);
    die('⚠️ Ocorreu um erro ao processar a requisição.');
}

// Decodifica a resposta JSON
$parsed = json_decode($response, true);

// Verifica se o token de acesso expirou ou é inválido
if (stripos($response, 'Token do usuário do SCPA inválido/expirado') !== false || stripos($response, 'Não autorizado') !== false || stripos($response, 'Unauthorized') !== false) {
    $config['sipni_token'] = get_bearer_sipni(); // Obtém um novo token de acesso
    $config = json_encode($config);
    file_put_contents('config.json', $config); // Atualiza o arquivo de configuração com o novo token
    header('Location: ' . $_SERVER['PHP_SELF'] . '?consulta=' . $cpf); // Redireciona para tentar a consulta novamente
    exit;
}

// Verifica se não há registros encontrados para o CPF
if (empty($parsed['records'])) {
    error_log('CPF não encontrado na API.');
    die('⚠️ CPF não encontrado.');
}

// Função para formatar a data no formato desejado
function formatarData($data) {
    $timestamp = strtotime($data);
    return date('d/m/Y', $timestamp);
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Dados do Cidadão</title>
    <style>
          body {
            background-color: #1c1e22;
            color: #fff;
            position: relative;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            font-family: 'Arial', sans-serif;
        }
        table {
            width: 100%;
            margin-top: 20px;
            border-collapse: collapse;
            border-radius: 10px;
            overflow: hidden;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #212529;
            color: #fff;
            font-weight: bold;
        }
        td {
            background-color: #1c1e22;
            color: #fff;
        }
        h1 {
            text-align: center;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <h1>Dados do Cidadão</h1>

    <div class="table-responsive">
        <table class="table table-dark table-striped">
            <thead>
                <tr>
                    <th colspan="2">👤 Dados Pessoais</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>CPF:</td>
                    <td><?php echo $parsed['records'][0]['cpf']; ?></td>
                </tr>
                <tr>
                    <td>CNS:</td>
                    <td><?php echo $parsed['records'][0]['cnsDefinitivo']; ?></td>
                </tr>
                <tr>
                    <td>Nome:</td>
                    <td><?php echo $parsed['records'][0]['nome']; ?></td>
                </tr>
                <tr>
                    <td>Nascimento:</td>
                    <td><?php echo formatarData($parsed['records'][0]['dataNascimento']); ?></td>
                </tr>
                <tr>
                    <td>Idade:</td>
                    <td><?php echo date_diff(date_create($parsed['records'][0]['dataNascimento']), date_create('today'))->y; ?></td>
                </tr>
                <tr>
                    <td>Gênero:</td>
                    <td><?php echo $parsed['records'][0]['sexo'] === 'M' ? 'Masculino' : 'Feminino'; ?></td>
                </tr>
                <tr>
                    <td>Óbito:</td>
                    <td><?php echo $parsed['records'][0]['obito'] ? 'Sim' : 'Não'; ?></td>
                </tr>
                <tr>
                    <td>Mãe:</td>
                    <td><?php echo isset($parsed['records'][0]['nomeMae']) ? $parsed['records'][0]['nomeMae'] : 'Sem informação'; ?></td>
                </tr>
                <tr>
                    <td>Pai:</td>
                    <td><?php echo isset($parsed['records'][0]['nomePai']) ? $parsed['records'][0]['nomePai'] : 'Sem informação'; ?></td>
                </tr>
            </tbody>
        </table>
    </div>

    <?php if (!empty($parsed['records'][0]['telefone'])): ?>
        <div class="table-responsive">
            <table class="table table-dark table-striped">
                <thead>
                    <tr>
                        <th colspan="2">📞 Telefones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($parsed['records'][0]['telefone'] as $telefone): ?>
                        <tr>
                            <td>Telefone:</td>
                            <td>(<?php echo $telefone['ddd']; ?>) <?php echo $telefone['numero']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <?php if (!empty($parsed['records'][0]['endereco'])): ?>
        <div class="table-responsive">
            <table class="table table-dark table-striped">
                <thead>
                    <tr>
                        <th colspan="6">🏠 Endereço</th>
                    </tr>
                    <tr>
                        <th>Logradouro</th>
                        <th>Número</th>
                        <th>Bairro</th>
                        <th>Município</th>
                        <th>Estado</th>
                        <th>CEP</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?php echo isset($parsed['records'][0]['endereco']['logradouro']) ? $parsed['records'][0]['endereco']['logradouro'] : 'Sem informação'; ?></td>
                        <td><?php echo isset($parsed['records'][0]['endereco']['numero']) ? $parsed['records'][0]['endereco']['numero'] : 'Sem informação'; ?></td>
                        <td><?php echo isset($parsed['records'][0]['endereco']['bairro']) ? $parsed['records'][0]['endereco']['bairro'] : 'Sem informação'; ?></td>
                        <td>
                            <?php
                                if (isset($parsed['records'][0]['endereco']['municipio'])) {
                                    $ch = curl_init();
                                    curl_setopt($ch, CURLOPT_URL, 'https://servicos-cloud.saude.gov.br/pni-bff/v1/municipio/' . $parsed['records'][0]['endereco']['municipio']);
                                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
                                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
                                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
                                    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                                    curl_setopt($ch, CURLOPT_ENCODING, "gzip");
                                    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                                        'User-Agent: Mozilla/5.0 (Windows NT ' . rand(11, 99) . '.0; Win64; x64) AppleWebKit/' . rand(111, 991) . '.' . rand(11, 99) . ' (KHTML, como Gecko) Chrome/' . rand(11, 99) . '.0.0.0 Safari/537.36',
                                        'Authorization: Bearer ' . $bearer_token,
                                        'DNT: 1',
                                        'Referer: https://si-pni.saude.gov.br/'
                                    ));
                                    $re_quatro = curl_exec($ch);
                                    $parsed_quatro = json_decode($re_quatro, true);

                                    if (isset($parsed_quatro['record']['nome'])) {
                                        echo $parsed_quatro['record']['nome'];
                                    } else {
                                        echo 'Sem informação';
                                    }
                                } else {
                                    echo 'Sem informação';
                                }
                            ?>
                        </td>
                        <td><?php echo isset($parsed['records'][0]['endereco']['siglaUf']) ? $parsed['records'][0]['endereco']['siglaUf'] : 'Sem informação'; ?></td>
                        <td><?php echo isset($parsed['records'][0]['endereco']['cep']) ? $parsed['records'][0]['endereco']['cep'] : 'Sem informação'; ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

</body>
</html>
