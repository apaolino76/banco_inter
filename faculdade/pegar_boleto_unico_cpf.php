<?php
    require_once 'classes/conexaoPrisma.php';
    require_once 'classes/BancoInter.php';

    if (empty($_GET['parcela'])) {
        echo "<script type='text/javascript'>alert('Erro! O valor da chave: CPF, não pode estar vazio.');</script>";
    } else {

        $parametros = [
            "" => $_GET["parcela"]
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