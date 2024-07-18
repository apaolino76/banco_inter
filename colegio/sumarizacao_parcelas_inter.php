<?php
    require_once 'classes/conexaoPrisma.php';
    require_once 'classes/BancoInter.php';
    require_once 'classes/BancoInterDados.php';

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
            "tipoOrdenacao" => 'ASC',
        ];

        try {       
            /* GERAR TOKEN BANCO INTER */
            $appInter = new BancoInter();
            $result   = $appInter->geraTokenDeAcesso();    
            $token    = $result->access_token;
    
            /* PEGA A QUANTIDADE DE PARCELAS GERADAS NO BANCO INTER */
            $items     = $appInter->pegarSumarioDePagamentosNoAppInter($token, $parametros);
            //var_dump($items);
            foreach ($items as $item){
                var_dump($item->situacao, $item->valor, $item->quantidade);
            }
        } catch (Exception $e) {
            echo "<script type='text/javascript'>alert('Erro: {$e->getMessage()}');</script>";
        }
    }

?>