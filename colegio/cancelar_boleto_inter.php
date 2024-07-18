<?php
    require_once 'classes/conexaoPrisma.php';
    require_once 'classes/BancoInter.php';
    require_once 'classes/BancoInterDadosColegio.php';

    if (!isset($_GET['cod_parcela'])) {
        echo "<script type='text/javascript'>alert('Erro! O valor da chave: Cód. Parcela, deve ser definido como parâmetro da URL.');</script>";
    } elseif (empty($_GET['cod_parcela'])) {
        echo "<script type='text/javascript'>alert('Erro! O valor da chave: Cód. Parcela, não pode estar vazio.');</script>";
    } else {
        try {       
            /* GERAR TOKEN BANCO INTER */
            $appInter = new BancoInter();
            $result   = $appInter->geraTokenDeAcesso();    
            $token    = $result->access_token;
    
            /* CANCELAR BOLETO DO BANCO INTER */
            $appPrisma = new BancoInterDadosColegio($mysqli);
            $linha     = $appPrisma->buscarCodSolicitacaoNoPrisma($_GET['cod_parcela']);
            $result    = $appInter->cancelarBoletoNoAppInter($token, $linha[0]["cod_solicitacao"], 1);
            $appPrisma->cancelarBoletoInterPrisma($_GET['cod_parcela']);
        } catch (Exception $e) {
            echo "<script type='text/javascript'>alert('Erro: " . $e->getMessage(). "');</script>";
        }
    }  
?>