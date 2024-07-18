<?php
    include("conexao_prisma.php");

    $SSL_Cert       = 'C:\wamp64\www\banco_inter\certificados_inter\Inter_API_Certificado.crt';
    $SSL_Key        = 'C:\wamp64\www\banco_inter\certificados_inter\Inter_API_Chave.key';
    $client_id      = 'cbcf5973-94de-4514-990b-6e49add16844';
    $client_secret  = 'ad1daf4a-17f1-4f62-a429-2dfde7467c0b';
    $conta_corrente = '350416990';
    $matricula      = '121000523';
    $competencia    = '06/2024';

    $query = "
		SELECT
			ic.matricula
			, ip.codigo cod_inscricao_periodo_letivo
			, a.codigo cod_aluno
			, a.nome
			, a.nome_resp_finan
            , a.email_resp_finan
            , SUBSTRING(a.tel_cel_resp_finan, 1, 3) ddd_resp_finan
            , SUBSTRING(a.tel_cel_resp_finan, 4, LENGTH(a.tel_cel_resp_finan)-3) tel_resp_finan        
			, p.codigo cod_parcela
			, p.numero
			, truncate(p.valor_pagar, 2) valor_pagar
			, p.num_boleto
			, p.msg_boleto
			, (p.quant_boletos + 1)quant_boletos
			, DATE_FORMAT(now(), '%d/%m/%Y') data_remessa
			, DATE_FORMAT(p.data_vencimento, '%m%y') competencia
			, DATE_FORMAT(p.data_vencimento, '%d/%m/%Y') data_vencimento_frm
            , p.data_vencimento
			, DATE_FORMAT(DATE_ADD(p.data_vencimento, INTERVAL 1 DAY), '%d/%m/%Y') data_multa
			, (SELECT tl.descricao
			   FROM tipo_logradouro tl
			   WHERE tl.codigo = a.cod_logradouro_cobranca)logr_cob
			, a.logradouro_cobranca descricao_logr_cob
			, a.numero_cobranca
			, a.complemento_cobranca
			, a.bairro_cobranca
			, a.cidade_cobranca
			, (SELECT u.sigla
			   FROM uf u
			   WHERE u.codigo = a.uf_cobranca)uf_cobranca
			, a.cep_cobranca
			, a.cpf_resp_finan cpf_resp_finan
            , b.codigo_solicitacao
        FROM 
			aluno a INNER JOIN inscricao_curso ic ON a.codigo = ic.cod_aluno
					INNER JOIN inscricao_periodo_letivo ip ON ic.matricula = ip.matricula
															  AND ic.cod_periodo_letivo_ing = ip.cod_periodo_letivo
					INNER JOIN situacao_aluno sa ON ic.matricula = sa.matricula
					INNER JOIN parcelas_pgto p ON ip.codigo = p.cod_inscricao_periodo_letivo
                    LEFT JOIN boleto_inter b ON p.codigo = b.cod_parcela_pgto
		WHERE
			sa.cod_situacao_atual not in (2, 3, 4, 5, 6, 7, 17)
			AND ic.matricula = '" . $matricula . "'
			AND p.tipo = 1
			AND p.cod_renegociacao is null
			AND p.data_pagamento is null
			AND DATE_FORMAT(p.data_vencimento, '%m/%Y') = '" . $competencia . "'
			AND sa.codigo = (SELECT max(sa1.codigo)
							 FROM situacao_aluno sa1
							 WHERE sa1.matricula = ic.matricula)
		ORDER BY 
			a.nome";
	$resultado = $mysqli->query($query) or die("Não foi possível realizar a Consulta 01.");
	$linha = $resultado->fetch_assoc();
    
    $valor_pagar      = number_format(round($linha["valor_pagar"], 2), 2);
    $valor_multa      = number_format(round(($linha["valor_pagar"] * 0.02), 2), 2);
    $valor_mora       = number_format(($valor_pagar * 0.0033), 2);

    $nome_UTF8        = mb_convert_encoding($linha["nome"],"UTF-8");;
    $tipo_logra       = trim($linha["logr_cob"]);
    $logradouro       = trim($linha["descricao_logr_cob"]);
    $endereco         = "{$tipo_logra} {$logradouro}";
    $endereco_UTF8    = mb_convert_encoding($endereco,"UTF-8");
    $cidade_UTF8      = mb_convert_encoding($linha["cidade_cobranca"],"UTF-8");
    $bairro_UTF8      = mb_convert_encoding($linha["bairro_cobranca"],"UTF-8");
    $complemento_UTF8 = mb_convert_encoding($linha["complemento_cobranca"],"UTF-8");
    $ddd_formatado    = intval($linha["ddd_resp_finan"]);

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

    if($linha["codigo_solicitacao"] == ""){

        // $valor_pagar

        $auth = "Authorization: Bearer {$bearerToken}";
        $data=<<<DATA
        { 
            "seuNumero":"{$linha["cod_parcela"]}",
            "valorNominal":2.50,
            "valorAbatimento": 0,
            "dataVencimento":"{$linha["data_vencimento"]}",
            "numDiasAgenda":35,
            "atualizarPagador":false,
            "pagador":{
                "cpfCnpj":"{$linha["cpf_resp_finan"]}",
                "tipoPessoa":"FISICA",
                "nome":"{$nome_UTF8}",
                "endereco":"{$endereco_UTF8}",
                "cidade":"{$cidade_UTF8}",
                "uf":"{$linha["uf_cobranca"]}",
                "cep":"{$linha["cep_cobranca"]}",
                "email":"{$linha["email_resp_finan"]}",
                "ddd":"{$ddd_formatado}",
                "telefone": "{$linha["tel_resp_finan"]}",
                "numero":"{$linha["numero_cobranca"]}",
                "complemento":"{$complemento_UTF8}",
                "bairro":"{$bairro_UTF8}"
            },
            "multa":{
                "valor":$valor_multa,
                "codigo":"VALORFIXO"
            },
            "mora":{
                "valor":$valor_mora,
                "codigo":"VALORDIA"
            },
            "mensagem":{
                "linha1":"Vencimento original: {$linha["data_vencimento_frm"]} Valor original: R$ {$linha["valor_pagar"]}",
                "linha2":"Pagamento ref. {$linha["numero"]} parcela",
                "linha3":"Nao receber apos o vencimento. MORA: 0,033% a.d MULTA: 2% a.m"
            } 
        }
        DATA;
    
        $auth = "Authorization: Bearer {$bearerToken}";
        $cc   = "x-conta-corrente: {$conta_corrente}";
        $json = 'Content-Type: application/json';
    
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://cdpj.partners.bancointer.com.br/cobranca/v3/cobrancas");
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array($auth,$cc,$json));
        curl_setopt($ch, CURLOPT_SSLCERT, $SSL_Cert);
        curl_setopt($ch, CURLOPT_SSLKEY, $SSL_Key);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
        $result = curl_exec($ch);
        $error  = curl_error($ch);
        $errno  = curl_errno($ch);
    
        curl_close ($ch);
    
        if ($error !== '') {
            throw new Exception($error);
        }

        //print $result . "\n"; 
    
        $obj = json_decode($result);
        $codigo_solicitacao = $obj->codigoSolicitacao;

        /* Prepara uma instrução de inserção */
        $stmt = $mysqli->prepare("INSERT INTO boleto_inter (codigo, codigo_parcela_pgto, codigo_solicitacao) VALUES (?,?,?)");

        /* Vincula variáveis aos parâmetros */
        $stmt->bind_param("sss", $val1, $val2, $val3);

        $val1 = 0;
        $val2 = $linha["cod_parcela"];
        $val3 = $codigo_solicitacao;

        /* Executa a instrução */
        $stmt->execute();

    } else {
        $codigo_solicitacao = $linha["codigo_solicitacao"];
    }    

    $auth = "Authorization: Bearer {$bearerToken}";
    $cc   = "x-conta-corrente: {$conta_corrente}";
    $json = 'Content-Type: application/json';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://cdpj.partners.bancointer.com.br/cobranca/v3/cobrancas/{$codigo_solicitacao}/pdf");
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
    
    $mysqli->close();
?>