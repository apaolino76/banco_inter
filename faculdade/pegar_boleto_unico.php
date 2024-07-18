<?php
    require_once 'classes/conexaoPrisma.php';
    require_once 'classes/BancoInter.php';

    if (!isset($_GET['data_inicial'])) {
        echo "<script type='text/javascript'>alert('Erro! O valor da chave: Data Inicial, deve ser definido como parâmetro da URL.');</script>";
    } elseif (empty($_GET['data_inicial'])) {
        echo "<script type='text/javascript'>alert('Erro! O valor da chave: Data Inicial, não pode estar vazio.');</script>";
    } elseif (!isset($_GET['data_final'])) {
        echo "<script type='text/javascript'>alert('Erro! O valor da chave: Data Final, deve ser definido como parâmetro da URL.');</script>";
    } elseif (empty($_GET['data_final'])) {
        echo "<script type='text/javascript'>alert('Erro! O valor da chave: Data Final, não pode estar vazio.');</script>";
    } elseif (!isset($_GET['seu_numero'])) {
        echo "<script type='text/javascript'>alert('Erro! O valor da chave: Seu Número, deve ser definido como parâmetro da URL.');</script>";
    } elseif (empty($_GET['seu_numero'])) {
        echo "<script type='text/javascript'>alert('Erro! O valor da chave: Seu Número, não pode estar vazio.');</script>";
    } else {

        $parametros = [
            "dataInicial" => $_GET["data_inicial"],
            "dataFinal"   => $_GET["data_final"],
            "seuNumero"   => $_GET["seu_numero"],
        ];
    
        try {       
            /* GERAR TOKEN BANCO INTER */
            $appInter = new BancoInter();
            $result   = $appInter->geraTokenDeAcesso();    
            $token    = $result->access_token;
    
            /* GERAR BAIXAS DE PAGAMENTO FEITAS NO BANCO INTER */
            $result   = $appInter->pegarBoletoUnicoNoAppInter($token, $parametros);
            //var_dump($result);
            var_dump($result->cobrancas[0]);
            /*
            header("Location: outra_pagina.php");
            exit;
            */
        } catch (Exception $e) {
            echo "<script type='text/javascript'>alert('Erro: " . $e->getMessage() . "!');</script>";
        }        
    }
?>