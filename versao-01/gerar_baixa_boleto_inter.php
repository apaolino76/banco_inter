<?php
    include("conexao_prisma.php");

    $SSL_Cert       = 'C:\wamp64\www\banco_inter\certificados_inter\Inter_API_Certificado.crt';
    $SSL_Key        = 'C:\wamp64\www\banco_inter\certificados_inter\Inter_API_Chave.key';
    $client_id      = 'cbcf5973-94de-4514-990b-6e49add16844';
    $client_secret  = 'ad1daf4a-17f1-4f62-a429-2dfde7467c0b';
    $conta_corrente = '350416990';
      
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL,"https://cdpj.partners.bancointer.com.br/oauth/v2/token");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_SSLCERT, $SSL_Cert);
    curl_setopt($ch, CURLOPT_SSLKEY, $SSL_Key);
    curl_setopt($ch, CURLOPT_POSTFIELDS, 
        http_build_query(array('client_id' => $client_id, 
                            'client_secret' => $client_secret, 
                            'scope' => 'boleto-cobranca.read', 
                            'grant_type' => 'client_credentials')));
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
                
    // Receive server response ...
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $server_response = curl_exec($ch);
    $error = curl_error($ch);
    $errno = curl_errno($ch);

    curl_close ($ch);

    if ($errno !== '') {
        throw new Exception($error);
    }

    if ($server_response == '') {
        throw new Exception("Resposta vazia, provavelmente o limite de chamadas foi atingido...");
    }

    $obj = json_decode($server_response);

    $bearerToken = $obj->{'access_token'};

    $auth = "Authorization: Bearer {$bearerToken}";
    $cc   = "x-conta-corrente: {$conta_corrente}";
    $json = "Content-Type: application/json";

    $queryString = http_build_query([
        'dataInicial' => '2024-06-05',
        'dataFinal' => date('Y-m-d') < '2024-06-05' ? date('Y-m-d', strtotime('+30 days')) : date('Y-m-d'),
        'situacao' => 'RECEBIDO',
        'tipoOrdenacao' => 'ASC',
        'itensPorPagina' => 10,
        'paginaAtual' => 2
    ]);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://cdpj.partners.bancointer.com.br/cobranca/v3/cobrancas?{$queryString}");
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
    curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array($auth, $cc, $json));
    curl_setopt($ch, CURLOPT_SSLCERT, $SSL_Cert);
    curl_setopt($ch, CURLOPT_SSLKEY, $SSL_Key);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $result = curl_exec($ch);
    $error = curl_error($ch);
    $errno = curl_errno($ch);

    curl_close ($ch);

    if ($error !== '') {
        throw new Exception($error);
    }

    // print "Lista de Cobrancas: ". $result . "\n";

    $obj = json_decode($result);
    $itens = $obj->cobrancas;

    $total = 0;
    foreach ($itens as $item)
    {
        if (floatval($item->cobranca->valorTotalRecebido) != 2.50 )
        {
            $q    = "UPDATE parcelas_pgto  SET data_pagamento = ?, cod_forma_pagamento = ?, valor_pago = ?,  data_credito = ? WHERE codigo = ?";
            $stmt = $mysqli->prepare($q);
            $stmt->bind_param("sidsi", $param1, $param2, $param3, $param4, $param5);
            $param1 = $item->cobranca->dataSituacao;
            $param2 = 43;
            $param3 = floatval($item->cobranca->valorTotalRecebido);
            $param4 = $item->cobranca->dataSituacao;
            $param5 = intval($item->cobranca->seuNumero);
            $stmt->execute();
            $stmt->close();

            $total++;
        }
    }

    if ($total == 0) {
        echo "<script language=JavaScript>alert('Não Já parcelas disponíveis para serem baixadas!');</script>";
    } else {
        echo "<script language=JavaScript>alert('Foram baixadas: {$total} parcelas na base de dados do Prisma!');</script>";
    }    

    $mysqli->close();
?>