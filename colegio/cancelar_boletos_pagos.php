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
    
            /* CANCELAR BOLETOS PAGOS ATRAVÉS DE OUTRA FORMA DE PAGAMENTO QUE NÃO SEJA VIA O BANCO INTER */
            $appPrisma = new BancoInterDadosColegio($mysqli, $_GET["competencia"]);
            $linhas    = $appPrisma->buscarParcelasPagasNoPrismaComOutraFormaPagto();
            // var_dump(count($linhas));
            $total     = 0;        
            foreach ($linhas as $linha){
                $resposta = $appInter->cancelarBoletoNoAppInter($token, $linha["cod_solicitacao"], 2);
                $appPrisma->cancelarBoletoInterPrisma($linha["codigo"]);
                $total++;
            }
            if ($total == 0) {
                echo "<script type='text/javascript'>alert('Não há parcelas disponíveis para serem canceladas!');</script>";
            } else {
                echo "<script type='text/javascript'>alert('Foram canceladas: " . $total . " parcelas no app do Banco Inter!');</script>";
            }
        } catch (Exception $e) {
            echo "<script type='text/javascript'>alert('Erro: " . $e->getMessage() . "');</script>";
        }
    }
?>