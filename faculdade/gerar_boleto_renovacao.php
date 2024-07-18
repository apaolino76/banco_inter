<?php
//    require_once "session.php";
    require_once "classes/conexaoPrisma.php";
    require_once "classes/BancoInterDadosFaculdade.php";
    require_once "classes/BancoInter.php";

//    $matricula      = $_SESSION["s_matricula"];
//    $periodo_letivo = $_SESSION["s_periodo_letivo_renovacao"];
    
    $matricula      = '2021100132707';
    $periodo_letivo = 10412;
/*
    var_dump($matricula);
    var_dump($periodo_letivo);
    var_dump($mysqli);
*/
   
    try {       
        // BUSCA A PARCELA GERADA DURANTE O PROCESSO DA RENOVAÇÃO 
        $appPrisma = new BancoInterDadosFaculdade($mysqli);        
        $linhas    = $appPrisma->buscarPrimeiraParcelaDaRenovacao($periodo_letivo, $matricula);
//        var_dump($linhas);
        if (empty($linhas)) {
            throw new Exception("Não há parcelas geradas para o novo Período Letivo");
        }
        
        $parametros = [
            "dataInicial"           => $linhas[0]['data_ini_matricula'],
            "dataFinal"             => $linhas[0]['data_fim_matricula'],
            "cpfCnpjPessoaPagadora" => $linhas[0]['cpf_resp_finan'],
            "situacao"              => "A_RECEBER",
            "tipoOrdenacao"         => "DESC",
        ];
//        var_dump($parametros);
        
        // GERAR TOKEN BANCO INTER 
        $appInter = new BancoInter();
        $result   = $appInter->geraTokenDeAcesso();
        $token    = $result->access_token;
//        var_dump($token);

        // PEGA O BOLETO DE ACORDO COM OS PARÂMETROS DA PESQUISA 
        $result = $appInter->pegarBoletoUnicoNoAppInter($token, $parametros);
//        var_dump($result->cobrancas);        

        if (!empty($result->cobrancas)) {
            foreach ($result->cobrancas as $item) {
//                var_dump($item->cobranca->codigoSolicitacao);
//                print_r($item->cobranca);
                if (!is_null($item->cobranca->codigoSolicitacao)) {
                    // CANCELAR BOLETO DO BANCO INTER 
                    $result = $appInter->cancelarBoletoNoAppInter($token, $item->cobranca->codigoSolicitacao, 1);            
//                    var_dump($result);
                }                    
            }
        }
            
        //* GERAR COBRANÇAS NO BANCO INTER
        $data = $appPrisma->gerarJsonComDadosDoPagador($linhas[0]);
//        print_r($data);
        $obj  = $appInter->geraCobrancaNoAppInter($token, $data);
        if(isset($obj->violacoes)){
            $mensagem = $obj->detail . '\n';
            $indice = 1;
            foreach ($obj->violacoes as $violacao) {
                $mensagem .= $indice . ") " . (isset($violacao->razao) ? $violacao->razao : "") . (isset($violacao->propriedade) ? " => " . $violacao->propriedade : "") . '\n';
                $indice++;
            }
            throw new Exception($mensagem);
        }
//        sleep(2);
        // VISUALIZAR O BOLETO DO BANCO INTER 
        $result  = $appInter->visualizarBoletoInter($token, $obj->codigoSolicitacao);
        $pdf     = base64_decode($result->pdf);
        $nomeArq = "boleto.pdf";
        header('Content-Type: application/pdf');
//        header("Content-Disposition: inline; filename=\"$nomeArq\"");
        header("Content-Disposition: inline; filename=boleto.pdf");
        echo $pdf;
    } catch (Exception $e) {
        echo "<script type='text/javascript'>alert('Erro: " . $e->getMessage() . "!');</script>";
    }
?>