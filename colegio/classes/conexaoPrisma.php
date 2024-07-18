<?php
   try {
      // Conexão Colégio
      $host     = '172.16.1.124';
      $user     = 'usuarioCisig';
      $password = 'usuarioCisig1519';
      $database = 'cisig';
      $port     = '3306';
     
      $mysqli = mysqli_init();
      $mysqli->real_connect($host, $user, $password, $database, $port, null, 2);
      if ($mysqli->connect_error) {
         throw new Exception("Conexão falhou: {$mysqli->connect_error}");
      }
      $mysqli->query("SET NAMES 'utf8'");
      $mysqli->query('SET character_set_connection=utf8');
      $mysqli->query('SET character_set_client=utf8');
      $mysqli->query('SET character_set_results=utf8');
   } catch (Exception $e) {
      die('<script type="text/javascript">alert("Erro : ' . str_replace(array("\r", "\n"), ' ', addslashes($e->getMessage())) . '!");</script>');
   }
?>
