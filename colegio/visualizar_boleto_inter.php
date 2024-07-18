<?php
    require_once 'classes/conexaoPrisma.php';
    require_once 'classes/BancoInter.php';

    if (!isset($_GET['solicitacao'])) {
        echo "<script type='text/javascript'>alert('Erro! O valor da chave: Solicitação, deve ser definido como parâmetro da URL.');</script>";
    } elseif (empty($_GET['solicitacao'])) {
        echo "<script type='text/javascript'>alert('Erro! O valor da chave: Solicitação, não pode estar vazio.');</script>";
    } else {
        
        try {       
            /* GERAR TOKEN BANCO INTER */
            $appInter = new BancoInter();
            $result   = $appInter->geraTokenDeAcesso();    
            $token    = $result->access_token;
    
            /* VISUALIZAR O BOLETO DO BANCO INTER */
            $result  = $appInter->visualizarBoletoInter($token, $_GET['solicitacao']);
            $pdf     = base64_decode($result->pdf);
            $nomeArq = "boleto.php";
            header('Content-Type: application/pdf');
            header("Content-Disposition: inline; filename=\"$nomeArq\"");
            echo $pdf;
        } catch (Exception $e) {
            echo "<script type='text/javascript'>alert('Erro: " . $e->getMessage() . "!');</script>";
        }
    }
?>