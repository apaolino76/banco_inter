<?php
    $SSL_Cert       = '/etc/pki/tls/certs/Inter_API_Certificado.crt';
    $SSL_Key        = '/etc/pki/tls/private/Inter_API_Chave.key';
    $client_id      = 'cbcf5973-94de-4514-990b-6e49add16844';
    $client_secret  = 'ad1daf4a-17f1-4f62-a429-2dfde7467c0b';
    $conta_corrente = '350416990';
    $solicitacao    = $_GET["cod_solicitacao"];

    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL,"https://cdpj.partners.bancointer.com.br/oauth/v2/token");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_SSLCERT, $SSL_Cert);
    curl_setopt($ch, CURLOPT_SSLKEY, $SSL_Key);
    curl_setopt($ch, CURLOPT_POSTFIELDS, 
        http_build_query(array('client_id' => $client_id, 
                            'client_secret' => $client_secret, 
                            'scope' => 'boleto-cobranca.write boleto-cobranca.read', 
                            'grant_type' => 'client_credentials')));
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
                
    // Receive server response ...
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $server_response = curl_exec($ch);
    $error = curl_error($ch);
    $errno = curl_errno($ch);

    curl_close ($ch);

    if ($error !== '') {
        throw new Exception($error);
    }

    if ($server_response == '') {
        throw new Exception("Resposta vazia, provavelmente o limite de chamadas foi atingido...\n");
    }

    $obj = json_decode($server_response);
    $bearerToken = $obj->{'access_token'};

    $auth = "Authorization: Bearer {$bearerToken}";
    $cc   = "x-conta-corrente: {$conta_corrente}";
    $json = 'Content-Type: application/json';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://cdpj.partners.bancointer.com.br/cobranca/v3/cobrancas/{$solicitacao}/pdf");
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

    $obj = json_decode($result);

    $pdf = base64_decode($obj->pdf);
    header('Content-Type: application/pdf');
    echo $pdf;    
?>