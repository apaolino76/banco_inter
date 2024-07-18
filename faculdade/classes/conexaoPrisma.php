<?php
   try {
      // Conexão Faculdade
      //$host     = '172.31.245.50';
      $host     = '54.233.171.249';
      $user     = 'sig2015_grad';
      $password = 'Nt!ladeira';
      $database = 'fisig';
      $port     = '3306';
      
      $mysqli = mysqli_init();
      $mysqli->real_connect($host, $user, $password, $database, $port, null, 2);
      if ($mysqli->connect_error) {
         throw new Exception("Conexão falhou: " . $mysqli->connect_error);
      }
      $mysqli->query("SET NAMES 'utf8'");
      $mysqli->query('SET character_set_connection=utf8');
      $mysqli->query('SET character_set_client=utf8');
      $mysqli->query('SET character_set_results=utf8');
   } catch (Exception $e) {
      die('<script type="text/javascript">alert("Erro : ' . str_replace(array("\r", "\n"), ' ', addslashes($e->getMessage())) . '!");</script>');
   }
?>
