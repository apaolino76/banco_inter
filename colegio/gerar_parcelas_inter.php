<?php
    require_once 'classes/conexaoPrisma.php';
    require_once 'classes/BancoInter.php';
    require_once 'classes/BancoInterDadosColegio.php';

    if (!isset($_GET['competencia'])) {
        echo "<script type='text/javascript'>alert('Erro! O valor da chave: Competência, deve ser definido como parâmetro da URL.');</script>";
    } elseif (empty($_GET['competencia'])) {
        echo "<script type='text/javascript'>alert('Erro! O valor da chave: Competência, não pode estar vazio.');</script>";
    } else {

        try {       
            /* GERAR TOKEN BANCO INTER */
            $appInter = new BancoInter();
            $result   = $appInter->geraTokenDeAcesso();    
            $token    = $result->access_token;
            
            /* GERAR COBRANÇAS NO BANCO INTER */
            $appPrisma = new BancoInterDadosColegio($mysqli, $_GET["competencia"]);
            $linhas    = $appPrisma->buscarParcelasPrismaPorCompetencia();
            $total     = 0;        
            foreach ($linhas as $linha){
                
                if(is_null($linha["cod_solicitacao"])){    
//                    var_dump($linha["cod_parcela"]);
                    $data = $appPrisma->gerarJsonComDadosDoPagador($linha);
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
                    $appPrisma->gravarDadosBoletoInter($linha, $obj->codigoSolicitacao);
                    $total++;
                }
            }
    
            if ($total == 0) {
                echo "<script type='text/javascript'>alert('Já constam parcelas geradas para a competência: " . $_GET["competencia"] . ", na base de dados do Banco Inter!');</script>";
            } else {
                echo "<script type='text/javascript'>alert('Nº de parcela(s) gerada(s): " . $total . ", na base de dados do Banco Inter!');</script>";
            }
            /*
            header("Location: outra_pagina.php");
            exit;
            */
        } catch (Exception $e) {
            echo "<script type='text/javascript'>alert('Erro: ". $e->getMessage() . "!');</script>";
        }
    }
?>