<?php
    include("conexao_prisma.php");

    function preparaDadosdoAluno($linha)
    {
        $valor_pagar   = round($linha["valor_pagar"], 2);
        $valor_multa   = round(($valor_pagar * 0.02), 2);
        $valor_mora    = round(($valor_pagar * 0.033)/100, 2);

        $tipo_logra    = trim($linha["logr_cob"]);
        $logradouro    = trim($linha["descricao_logr_cob"]);
        $endereco      = "{$tipo_logra} {$logradouro}";
        $ddd_formatado = intval($linha["ddd_resp_finan"]);

        $data=<<<DATA
        { 
            "seuNumero":"{$linha["cod_parcela"]}",
            "valorNominal":{$valor_pagar},
            "valorAbatimento": 0,
            "dataVencimento":"{$linha["data_vencimento"]}",
            "numDiasAgenda":35,
            "atualizarPagador":false,
            "pagador":{
                "cpfCnpj":"{$linha["cpf_resp_finan"]}",
                "tipoPessoa":"FISICA",
                "nome":"{$linha["nome"]}",
                "endereco":"{$endereco}",
                "cidade":"{$linha["cidade_resp_finan"]}",
                "uf":"{$linha["uf_resp_finan"]}",
                "cep":"{$linha["cep_resp_finan"]}",
                "email":"{$linha["email_resp_finan"]}",
                "ddd":"{$ddd_formatado}",
                "telefone": "{$linha["tel_resp_finan"]}",
                "numero":"{$linha["numero_resp_finan"]}",
                "complemento":"{$linha["complemento_resp_finan"]}",
                "bairro":"{$linha["bairro_resp_finan"]}"
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
                "linha1":"Vencimento original: {$linha["data_vencimento_frm"]}",
                "linha2":"Valor original: R$ {$linha["valor_pagar"]}",
                "linha3":"Pagamento ref. {$linha["numero"]}ª parcela",
                "linha4":"Não receber após o vencimento. MORA: 0,033% a.d MULTA: 2% a.m"
            } 
        }
        DATA;

        return $data;        
    }

    function geraTokenDeAcesso($SSL_Cert, $SSL_Key, $client_id, $client_secret)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,"https://cdpj.partners.bancointer.com.br/oauth/v2/token");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_SSLCERT, $SSL_Cert);
        curl_setopt($ch, CURLOPT_SSLKEY, $SSL_Key);
        curl_setopt($ch, CURLOPT_POSTFIELDS, 
            http_build_query(array('client_id' => $client_id, 
                                'client_secret' => $client_secret, 
                                'scope' => 'boleto-cobranca.write', 
                                'grant_type' => 'client_credentials')));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $server_response = curl_exec($ch);
        $error = curl_error($ch);
        // $errno = curl_errno($ch);

        curl_close ($ch);

        if ($error !== '') {
            throw new Exception($error);
        }

        if ($server_response == '') {
            throw new Exception("Resposta vazia, provavelmente o limite de chamadas foi atingido...\n");
        }

        $obj = json_decode($server_response);
        return $obj->{'access_token'};
    }

    function geraCobrancaNoAppInter($bearerToken, $contaCorrente, $SSL_Cert, $SSL_Key, $data)
    {
        $auth = "Authorization: Bearer {$bearerToken}";
        $cc   = "x-conta-corrente: {$contaCorrente}";
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

        $obj = json_decode($result);
        var_dump($obj);
        return $obj->codigoSolicitacao;
    }

    function gravaDadosTabelaBoletosInter($conexao, $param1, $param2, $param3)
    {
        $q    = "INSERT INTO boleto_inter (codigo, cod_parcela_pgto, cod_solicitacao) VALUES (?, ?, ?)";
        $stmt = $conexao->prepare($q);
        $stmt->bind_param("iis", $param1, $param2, $param3);                
        $stmt->execute();
        $stmt->close();    
    }   

    $SSL_Cert       = 'C:\wamp64\www\banco_inter\certificados_inter\Inter_API_Certificado.crt';
    $SSL_Key        = 'C:\wamp64\www\banco_inter\certificados_inter\Inter_API_Chave.key';
    $client_id      = 'cbcf5973-94de-4514-990b-6e49add16844';
    $client_secret  = 'ad1daf4a-17f1-4f62-a429-2dfde7467c0b';
    $conta_corrente = '350416990';
    $competencia    = $_GET["competencia"];

    $query = "
		SELECT
            p.codigo cod_parcela
            , truncate(p.valor_pagar, 2) valor_pagar
            , p.data_vencimento
            , a.cpf_resp_finan
			, a.nome
			, (SELECT tl.descricao
               FROM tipo_logradouro tl
               WHERE tl.codigo = a.cod_logradouro_resp_finan)logr_cob
			, a.logradouro_resp_finan descricao_logr_cob
			, a.cidade_resp_finan
			, (SELECT u.sigla
               FROM uf u
               WHERE u.codigo = a.uf_resp_finan) uf_resp_finan
            , a.cep_resp_finan
            , a.email_resp_finan
            , SUBSTRING(a.tel_cel_resp_finan, 1, 3) ddd_resp_finan
            , SUBSTRING(a.tel_cel_resp_finan, 4, LENGTH(a.tel_cel_resp_finan)-3) tel_resp_finan 
			, a.numero_resp_finan
			, a.complemento_resp_finan
			, a.bairro_resp_finan
			, DATE_FORMAT(p.data_vencimento, '%d/%m/%Y') data_vencimento_frm
			, p.numero
            , b.cod_solicitacao
			, a.nome_resp_finan    
        FROM 
			aluno a INNER JOIN inscricao_curso ic ON a.codigo = ic.cod_aluno
					INNER JOIN inscricao_periodo_letivo ip ON ic.matricula = ip.matricula
															  AND ic.cod_periodo_letivo_ing = ip.cod_periodo_letivo
					INNER JOIN situacao_aluno sa ON ic.matricula = sa.matricula
					INNER JOIN parcelas_pgto p ON ip.codigo = p.cod_inscricao_periodo_letivo
                    LEFT JOIN boleto_inter b ON p.codigo = b.cod_parcela_pgto
		WHERE
			sa.cod_situacao_atual not in (2, 3, 4, 5, 6, 7, 17)
			AND p.tipo = 1
			AND p.cod_renegociacao is null
			AND p.data_pagamento is null
			AND EXTRACT(YEAR_MONTH FROM p.data_vencimento) = {$competencia}
			AND sa.codigo = (SELECT max(sa1.codigo)
							 FROM situacao_aluno sa1
							 WHERE sa1.matricula = ic.matricula)
		ORDER BY 
			a.nome";
	$resultado = $mysqli->query($query) or die("{$mysqli->connect_errno} - {$mysqli->connect_error}");
	$linhas = $resultado->fetch_all(MYSQLI_ASSOC);

    $total = 0;
    $token = geraTokenDeAcesso($SSL_Cert, $SSL_Key, $client_id, $client_secret);

    foreach ($linhas as $linha) {
        if(is_null($linha["cod_solicitacao"]))
        {

            $data = preparaDadosdoAluno($linha);
            /*
            $dados = json_decode($data);
            var_dump($dados);
            */
            $codSol = geraCobrancaNoAppInter($token, $conta_corrente, $SSL_Cert, $SSL_Key, $data);
            // var_dump($codSol);
            gravaDadosTabelaBoletosInter($mysqli, 0, $linha["cod_parcela"], $codSol);
            $total++;
        }    
    }

    if ($total == 0) {
        echo "<script language=JavaScript>alert('Já constam parcelas geradas para a competência: {$competencia}, na base de dados do Banco Inter!');</script>";
    } else {
        echo "<script language=JavaScript>alert('Foram gerados: {$total} parcelas, na base de dados do Banco Inter!!');</script>";     
    }    

    $mysqli->close();
?>