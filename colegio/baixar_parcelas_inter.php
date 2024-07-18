<?php
    require_once 'classes/conexaoPrisma.php';
    require_once 'classes/BancoInter.php';
    require_once 'classes/BancoInterDadosColegio.php';

    if (!isset($_GET['data_inicial'])) {
        echo "<script type='text/javascript'>alert('Erro! O valor da chave: Data Inicial, deve ser definido como parâmetro da URL.');</script>";
    } elseif (empty($_GET['data_inicial'])) {
        echo "<script type='text/javascript'>alert('Erro! O valor da chave: Data Inicial, não pode estar vazio.');</script>";
    } elseif (!isset($_GET['data_final'])) {
        echo "<script type='text/javascript'>alert('Erro! O valor da chave: Data Final, deve ser definido como parâmetro da URL.');</script>";
    } elseif (empty($_GET['data_final'])) {
        echo "<script type='text/javascript'>alert('Erro! O valor da chave: Data Final, não pode estar vazio.');</script>";
    } else {

        $parametros = [
            "dataInicial"   => $_GET["data_inicial"],
            "dataFinal"     => $_GET["data_final"],
            "situacao"      => 'RECEBIDO',
            "tipoOrdenacao" => 'ASC',
        ];

        try {       
            /* GERAR TOKEN BANCO INTER */
            $appInter = new BancoInter();
            $result   = $appInter->geraTokenDeAcesso();    
            $token    = $result->access_token;
    
            /* GERAR BAIXAS DE PAGAMENTO FEITAS NO BANCO INTER */
            $appPrisma = new BancoInterDadosColegio($mysqli);
            $result    = $appInter->pegarSumarioDePagamentosNoAppInter($token, $parametros);
            $numeroPag = ceil($result[0]->quantidade/100);
            $total     = 0;        
            for ($i = 0; $i <= $numeroPag - 1; $i++) {
                $parametros["paginacao.paginaAtual"] = $i; 
                $result = $appInter->pegarBoletosPagosNoAppInter($token, $parametros);
                $itens  = $result->cobrancas;
                foreach ($itens as $item){
//                    var_dump($item->cobranca->pagador->nome);
                    if (floatval($item->cobranca->valorTotalRecebido) != 2.50 ) {
                        $result = $appPrisma->baixarParcelasPrisma($item);
                        if ($result > 0) {
                            $total++;
                        }
                    }
                }            
            }
            if ($total == 0) {
                echo "<script type='text/javascript'>alert('Não há parcelas disponíveis para serem baixadas!');</script>";
            } else {
                echo "<script type='text/javascript'>alert('Foram baixadas: " . $total . " parcelas na base de dados do Prisma!');</script>";
            }
    
            /*
            header("Location: outra_pagina.php");
            exit;
            */
        } catch (Exception $e) {
            echo "<script type='text/javascript'>alert('Erro: " . $e->getMessage() . "');</script>";
        }
    }

?>