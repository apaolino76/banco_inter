<?php
	include("session.php");
	include("conexao_prisma.php");
	
	function Zeros($texto, $tam1, $tam2){
    	for($i = 1; $i <= $tam1 - strlen($texto); $i++){
			$texto = '0'.$texto;
		}
    	return $texto;
	}
	
	function nome($texto){
		$texto1 = '';
    	for($i = 0; $i < strlen($texto); $i++){
			if ($texto[$i] != ' '){
				$texto1 = $texto1.$texto[$i];
			}
			else
				break;		    	
		}
		return $texto1;
	}	

?>
<html>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
	<title>:: Faculdade Internacional Signorelli ::</title>
	<link href="css/estilo_pag.css" rel="stylesheet" type="text/css">
	<script src="js/jquery-1.3.2.min.js" type="text/javascript"></script>
</head>

<body>
	<?php
		$matricula = $_SESSION["s_matricula"];
		$cod_periodo_letivo = $_SESSION["s_periodo_letivo_renovacao"];
	//echo "=============================> {$cod_periodo_letivo}";		
		$opcao = $_POST["checkbox_acordo"];
		$sistema_cred = $_POST["hf_sistemacred"];

		if ($opcao == '') {
			mysql_close($db);
			echo "<script language=JavaScript>alert('O campo De acordo deve ser preenchido.');</script>";
			echo "<script language=JavaScript>javascript:history.go(-1);</script>";
		} else {
			$sql = "update inscricao_periodo_letivo set data_contrato = now()
					where matricula = '" . $matricula . "' and 
						  cod_periodo_letivo = " . $cod_periodo_letivo;
			mysql_query($sql) or die("Não foi possível realizar a consulta 00");
						
			$sql_boleto = "
				SELECT
					(CASE WHEN NOW() < '2020-02-14' THEN 0 							 -- BOLETO ITAÚ 
						  WHEN NOW() >= '2020-02-14' AND NOW() < '2025-01-02' THEN 1 -- BOLETO BANCO DO BRASIL
					 ELSE 2															 -- BOLETO BANCO SANTANDER
					 END) tipo_boleto				
					, ic.matricula
					, ip.codigo cod_inscricao_periodo_letivo
					, a.codigo cod_aluno
					, a.nome
					, a.nome_resp_finan
					, p.codigo cod_parcela
					, p.numero
					, round(p.valor_pagar, 2)valor_pagar
					, p.num_boleto
					, (p.quant_boletos + 1)quant_boletos
					, DATE_FORMAT(now(), '%d/%m/%Y') data_remessa
					, DATE_FORMAT(p.data_vencimento, '%m%y') competencia
					, DATE_FORMAT(p.data_vencimento, '%d/%m/%Y') data_vencimento
					, DATE_FORMAT(DATE_ADD(p.data_vencimento, INTERVAL 1 DAY), '%d/%m/%Y') data_multa
					, (SELECT tl.descricao
					   FROM tipo_logradouro tl
					   WHERE tl.codigo = a.cod_logradouro_cobranca)logr_cob
					, a.logradouro_cobranca descricao_logr_cob
					, a.numero_cobranca
					, a.complemento_cobranca
					, a.bairro_cobranca
					, a.cidade_cobranca
					, (SELECT u.sigla
					   FROM uf u
					   WHERE u.codigo = a.uf_cobranca)uf_cobranca
					, a.cep_cobranca
					, a.cpf_resp_finan
					, DATE_FORMAT(current_date,'%d/%m/%Y') as hoje
					, DATEDIFF(current_date,p.data_vencimento) as vencido
					-- , DATE_FORMAT(DATE_ADD(p.data_vencimento, INTERVAL (DATEDIFF(current_date,p.data_vencimento) + 2) DAY),'%d/%m/%Y') AS data_vcto
					, DATE_FORMAT(current_date,'%d/%m/%Y') as data_vcto
					, DATE_FORMAT(p.data_vencimento,'%w') AS dia_vcto
				FROM 
					aluno a INNER JOIN inscricao_curso ic ON a.codigo = ic.cod_aluno
							INNER JOIN inscricao_periodo_letivo ip ON ic.matricula = ip.matricula
							INNER JOIN situacao_aluno sa ON ic.matricula = sa.matricula
							INNER JOIN parcelas_pgto p ON ip.codigo = p.cod_inscricao_periodo_letivo
				WHERE 
					-- qtde de dias de atraso para visualizar o boleto
					(DATEDIFF(current_date,p.data_vencimento) < 34)
					AND sa.cod_situacao_atual not in (2, 3, 4, 5, 6, 7, 17)
					AND ic.matricula = '".$matricula."'
					AND ip.cod_periodo_letivo = ".$cod_periodo_letivo."
					AND p.tipo = 1
					AND p.numero = 1
					AND p.cod_renegociacao is null
					AND p.data_pagamento is null
					-- AND p.num_boleto is not null
					/* AND DATE_FORMAT(p.data_vencimento, '%m/%Y') = '".$competencia."' */
					AND sa.codigo = (SELECT max(sa1.codigo)
									 FROM situacao_aluno sa1
									 WHERE sa1.matricula = ic.matricula)
				ORDER BY
					a.nome";
					
			// echo $sql_boleto;
					
			$resultado_boleto = mysql_query($sql_boleto) or die(mysql_error());
			$linha_boleto = mysql_fetch_assoc($resultado_boleto);
			
			if(mysql_num_rows($resultado_boleto) == 0){
				echo "<script language=JavaScript>alert('O Boleto de Pagamento ainda não foi gerado.');</script>";
				echo "<script language=JavaScript>javascript:FecharJanela();</script>";
			}
			else {
				if ($linha_boleto["quant_boletos"] >= 20) {
					echo "<script language=JavaScript>alert('O limite de boletos gerados foi atingido! Entre em contato com o financeiro.');</script>";
					echo "<script language=JavaScript>javascript:FecharJanela();</script>";
				} else {
						
					if ($linha_boleto["vencido"] > 0){
						$data_vcto = $linha_boleto["hoje"];
						if ($linha_boleto["vencido"] <= 30){
							$mes_multa = 1;
						} 
						else {
							$mes_multa = 2;
						}
					  
						if($linha_boleto["dia_vcto"] == 0 && $linha_boleto["vencido"] == 1){
							$valor_cobrado = $linha_boleto["valor_pagar"];  
							$data_vcto = $linha_boleto["data_vencimento"];
						} 
						else if ($linha_boleto["dia_vcto"] == 6 && $linha_boleto["vencido"] == 2){
							$valor_cobrado = $linha_boleto["valor_pagar"];
							$data_vcto = $linha_boleto["data_vencimento"];
						}
						else {
							$valor_cobrado = $linha_boleto["valor_pagar"] + (($linha_boleto["valor_pagar"]*($mes_multa * 0.02)) + ($linha_boleto["valor_pagar"]*(($linha_boleto["vencido"]+2) * 0.00033)));  
							$msg = "Boleto atualizado"." - ".
								   "Vencimento original: ".$linha_boleto["data_vencimento"]." - ".
								   "Valor original: R$ ".$linha_boleto["valor_pagar"];
						} 
					} else{
						$valor_cobrado = $linha_boleto["valor_pagar"];  
						$data_vcto = $linha_boleto["data_vencimento"];
					}
					
					$valor_cobrado = round($valor_cobrado,2);
					//	$data_vcto = $linha_boleto["data_vcto"];
					//	echo 'antes '.str_replace(".", "", number_format($valor_cobrado,2))	;	
					//		echo 'wwwwwwwwwww'.str_replace(",","",str_replace(".", "", number_format($valor_cobrado,2)));
					//		echo 'rrrrrrrrrrrr'.number_format($linha_boleto["valor_pagar"],2,",",".")."<br>";
					//		echo 'dddddddddddd'.$linha_boleto["valor_pagar"];
					//break;
					$controle = $linha_boleto["quant_boletos"].$linha_boleto["competencia"].$linha_boleto["cod_aluno"];
					$multa = $valor_cobrado * 0.02;  
					$mora  = ($valor_cobrado * 0.033)/100;
					$mensagem = $msg."  Pagamento referente a ".$linha_boleto["numero"]."ª parcela"."<br>".
									 "MORA DIARIA: R$   ".number_format($mora, 2, ',', '')." a.d. - INSTRUCOES "."<br>"."MULTA - R$ ".number_format($multa, 2, ',', '');
					
					$sql = "update parcelas_pgto set quant_boletos = quant_boletos + 1
							where codigo = ".$linha_boleto["cod_parcela"];
					mysql_query($sql) or die ("Não foi possível realizar a consulta 02");
				}		
			}
		}
	?>
	<div id="pagina">
		<div id="cabecalho">
			<div id="cab_esq">:: Renova&ccedil;&atilde;o de Matr&iacute;cula - Finalizada ::</div>
			<div id="cab_dir">
				<div align="center">
					<a href="<?php echo 'renovacao_matricula.php?sistema_cred=' . $sistema_cred; ?>" target="area">
						<img src="imagens/porta.gif" alt="Sair" width="40" height="40" border="0">
					</a>
				</div>
			</div>
		</div>
		<div id="principal">
			<div id="barra_ferramentas">
				<div align="justify">
					<p>Observa&ccedil;&atilde;o:</p> 
					<p>- Sua matr&iacute;cula s&oacute; ser&aacute; validada ap&oacute;s o pagamento do boleto referente a primeira parcela da semestralidade;</p>
					<p> - O Contrato de Presta&ccedil;&atilde;o de Servi&ccedil;os s&oacute; estar&aacute; dispon&iacute;vel para impress&atilde;o (m&oacute;dulo Financeiro) ap&oacute;s 72 horas do pagamento  da primeira parcela da semestralidade.</p>
				</div>
			</div>
			<div id="imp_boleto">
				<?php
				if ($linha_boleto['tipo_boleto'] == 1) {?>
					<form id="pagamento" action="https://mpag.bb.com.br/site/mpag/" method="post" name="pagamento" target="_blank">
					
						<input type="hidden" name="refTran" value="<?= '2928236' . Zeros($controle, 10, strlen($controle)); ?>" />
						<input type="hidden" name="cod_aluno" value="<?= $linha_boleto["cod_aluno"]; ?>" />
						<input type="hidden" name="cod_parcela" value="<?= $linha_boleto["cod_parcela"]; ?>" />
						<input type="hidden" name="idConv" value="313988" />
						<input type="hidden" name="nome" value="<?= $linha_boleto["nome"]; ?>" />						
						<input type="hidden" name="qtdPontos" value="<?= Zeros('0', 15, 1); ?>" />
						<input type="hidden" name="tpPagamento" value= "2" />
						<input type="hidden" name="tpDuplicata" value= "DS" />
						<input type="hidden" name="indicadorPessoa" value= "1" />						
						<input type="hidden" name="cpfCnpj" value="<?= $linha_boleto["cpf_resp_finan"]; ?>">
						<input type="hidden" name="urlRetorno" value="home.php" />
						<input type="hidden" name="urlInforma" value="" />
						<input type="hidden" name="msgLoja" value="<?= $mensagem; ?>" />
						<input type="hidden" name="dtVenc" value="<?= str_replace("/", "", $data_vcto); ?>" />
						<input type="hidden" name="valor" value="<?= str_replace(",", "", str_replace(".", "", number_format($valor_cobrado,2))); ?>">
						<input type="hidden" name="endereco" value="<?= $linha_boleto["logr_cob"] . ' ' . $linha_boleto["descricao_logr_cob"] . ' ' . $linha_boleto["numero_cobranca"] . ' ' . $linha_boleto["complemento_cobranca"]; ?>" />
						<input type="hidden" name="cidade" value="<?= $linha_boleto["cidade_cobranca"]; ?>" />
						<input type="hidden" name="cep" value="<?= $linha_boleto["cep_cobranca"]; ?>">
						<input type="hidden" name="uf" value="<?= $linha_boleto["uf_cobranca"]; ?>">
				
						<table width="100%" border="0" cellpadding="0" cellspacing="1" bordercolor="#006699" bgcolor="#006699">
							<tr>
								<th colspan="4" scope="col">Informa&ccedil;&otilde;es do Boleto</th>
							</tr>
							<tr>
								<td align="justify" colspan="4">
									<span class="obrigatorio">Aten&ccedil;&atilde;o: A partir de 14/02/2020, confirme se a via de seu boleto na Plataforma Educacional foi emitida pelo Banco do Brasil, em caso negativo, N&Atilde;O PAGUE esse boleto e entre em contato com o setor financeiro imediatamente.</span>
								</td>
							</tr>		
							<tr>
								<td align="right">Refer&ecirc;ncia :</td>
								<td><?php echo '2928236'.Zeros($controle, 10, strlen($controle));?></td>
								<td align="right">Conv&ecirc;nio :</td>
								<td>313988</td>
							</tr>
							<tr>
								<td align="right" width="15%">Nome :</td>
								<td colspan="3"><?php echo $linha_boleto["nome"];?></td>						
							</tr>
							<tr>
								<td align="right">Dt. Venct&ordm;. :</td>
								<td><?php echo $data_vcto;//$linha_boleto["data_vencimento"];?></td>
								<td align="right" width="15%">Valor :</td>
								<td><?php echo number_format($valor_cobrado,2,",",".");//number_format($linha_boleto["valor_pagar"],2,",",".");//number_format($valor_cobrado,2);//$linha_boleto["valor_pagar"];?></td>
							</tr>
							<tr>
								<td align="right" width="15%">Endere&ccedil;o :</td>
								<td colspan="3"><?php echo $linha_boleto["logr_cob"].' '.$linha_boleto["descricao_logr_cob"].' '.$linha_boleto["numero_cobranca"].' '.$linha_boleto["complemento_cobranca"];?></td>
							</tr>
							<tr>
								<td align="right" width="15%">Cidade :</td>
								<td><?php echo $linha_boleto["cidade_cobranca"];?></td>
								<td align="right">Controle :</td>
								<td><?php echo $controle;?></td>
							</tr>
							<tr>
								<td align="right" width="15%">CEP :</td>
								<td width="15%"><?php echo $linha_boleto["cep_cobranca"];?></td>
								<td align="right" width="15%">UF :</td>
								<td><?php echo $linha_boleto["uf_cobranca"];?></td>
							</tr>
							<tr>
								<td colspan="4" align="center">
									<input name="button" type="submit" class="botao" id="button" value="Gerar Boleto">
								</td>
							</tr>
						</table>
					</form>
				<?php
				} else if ($linha_boleto['tipo_boleto'] == 2) {?>
				
					<form id="frmDados" action='https://api.getnet.com.br/v1/payments/boleto' method='POST' name="pagamento" target="_blank"> 
						<table width="100%" border="0" cellpadding="0" cellspacing="1" bordercolor="#006699" bgcolor="#006699">
							<tr>
								<td colspan="2">
									<table width="100%" border="0" cellpadding="0" cellspacing="1" bordercolor="#006699" bgcolor="#006699">
										<tr>
											<th colspan="4" scope="col">Informa&ccedil;&otilde;es do Boleto</th>
										</tr>
										<tr>
											<td align="justify" colspan="4">
												<span class="obrigatorio">Aten&ccedil;&atilde;o: A partir de 05/07/2023, confirme se a via de seu boleto na Plataforma Educacional foi emitida pelo Banco Santander, em caso negativo, N&Atilde;O PAGUE esse boleto e entre em contato com o setor financeiro imediatamente.</span>
											</td>
										</tr>
										<tr>
											<td align="right">Refer&ecirc;ncia :</td>
											<td><?php echo '288487' . Zeros($controle, 10, strlen($controle)); ?></td>
											<td align="right">Conv&ecirc;nio :</td>
											<td>0288487</td>
										</tr>
										<tr>
											<td align="right" width="15%">Nome :</td>
											<td colspan="3"><?php echo $linha_boleto["nome"];?></td>
										</tr>
										<tr>
											<td align="right">Dt. Venct&ordm;. :</td>
											<td><?php echo $linha_boleto["data_vencimento"];?></td>
											<td align="right" width="15%">Valor :</td>
											<td><?php echo str_replace(".", ",", $linha_boleto["valor_pagar"]);//echo $linha_boleto["valor_pagar"];?></td>
										</tr>
										<tr>
											<td align="right" width="15%">Endere&ccedil;o :</td>
											<td colspan="3"><?php echo $linha_boleto["logr_cob"] . ' ' . $linha_boleto["descricao_logr_cob"] . ' ' . $linha_boleto["numero_cobranca"] . ' ' . $linha_boleto["complemento_cobranca"];?></td>
										</tr>
										<tr>
											<td align="right" width="15%">Cidade :</td>
											<td><?php echo $linha_boleto["cidade_cobranca"]; ?></td>
											<td align="right">Controle :</td>
											<td><?php echo $controle; ?></td>
										</tr>
										<tr>
											<td align="right" width="15%">CEP :</td>
											<td width="15%"><?php echo $linha_boleto["cep_cobranca"]; ?></td>
											<td align="right" width="15%">UF :</td>
											<td><?php echo $linha_boleto["uf_cobranca"]; ?></td>
										</tr>
									</table>
								</td>
							</tr>
							<tr>
								<td colspan="2" align="center">
									<!-- <input name="button" type="submit" class="botao" id="button" value="Gerar Boleto"> -->
									<input name="buttonSantander" type="button" class="botao" id="buttonSantander" value="Gerar Boleto"></td>
								</td>
							</tr>
						</table>
					</form>
					<script type="text/javascript">
						$(document).ready(function(){ 

							$('#buttonSantander').click(function () {
								// autenticar - retornar token
							
								$.ajax({
									beforeSend: function(xhr){
										xhr.setRequestHeader('Authorization', 'Basic '+ btoa('33bb530a-f5b2-4a17-b115-f503d912113c:27903b16-3894-43ba-87a3-e26de15ca125')); // Signorelli
										// xhr.setRequestHeader('Authorization', 'Basic '+ btoa('dbe3d219-a145-416c-a25c-a351be4af775:9749036d-27e0-4059-8989-c557aa2e7c94'));	   // ICAPE	
									}, 
									type: 'POST', 
									dataType: 'json',
									contentType: 'application/x-www-form-urlencoded',
									url: 'https://api.getnet.com.br/auth/oauth/v2/token',
									data: { 
										scope: 'oob',
										grant_type: 'client_credentials'
									},
									success: function( msg ) {
										// Registrar boleto
										var access_token = msg.access_token;
										var token_type = msg.token_type;
										var url2 = $('#frmDados').attr('action');
									
										var my_order_id = '<?php echo date('His') . $linha_boleto['cod_aluno'];?>';
										var my_document_number = '<?php echo str_pad($linha_boleto['cod_parcela'], 9, '0', STR_PAD_RIGHT) . strlen($linha_boleto['cod_parcela']) . '00000';?>';
									
										<?php $linha_boleto['nome_resp_finan'] = str_replace("'","",$linha_boleto['nome_resp_finan']);?>
										<?php $linha_boleto['descricao_logr_cob'] = str_replace("'","",$linha_boleto['descricao_logr_cob']);?>

										//alert(my_order_id);		
										//alert(my_document_number);

										var data2 = { 
											seller_id: '2428c3dc-bc12-4a82-8a1b-6484733797af', // Signorelli
											// seller_id: 'b285cae3-3d69-4df0-9622-37eb0ba700f5', // Icape
											amount: <?php echo ($valor_cobrado*100);?>,
											currency: 'BRL',
											order: {
												order_id : my_order_id,
												sales_tax: 0,
												product_type: 'service'
											},
											boleto: {
												document_number: my_document_number,
												expiration_date: '<? echo $linha_boleto['data_vcto']; ?>',
												instructions: 'N&atilde;o receber ap&oacute;s o Vencimento. \n Boleto atualizado. \n Vencimento original: <? echo $linha_boleto['data_vencimento']; ?> \n MORA: 0,033% a.d \n MULTA: 2% a.m', 
												provider: 'santander'
											},
											customer: {
												first_name: '<?= substr(nome($linha_boleto['nome_resp_finan']),0,20); ?>',
												name: '<?= $linha_boleto['nome_resp_finan']; ?>',
												document_type: 'CPF',
												document_number: '<?= $linha_boleto['cpf_resp_finan']; ?>',
												billing_address: {
													street: '<?php echo $linha_boleto['logr_cob'].' '.$linha_boleto['descricao_logr_cob'];?>',
													number: '<?php echo $linha_boleto['numero_cobranca']; ?>',
													complement: '<?php echo $linha_boleto['complemento_cobranca']; ?>',
													district: '<?php echo $linha_boleto['bairro_cobranca']; ?>',
													city: '<?php echo $linha_boleto['cidade_cobranca']; ?>',
													state: '<?php echo $linha_boleto['uf_cobranca']; ?>',
													postal_code: '<?php echo $linha_boleto['cep_cobranca']; ?>'
												}										
											}
										};
								
										$.ajax({
											beforeSend: function(xhr){
												// console.log(JSON.stringify(data2));
												// console.log(token_type+' '+access_token);
												xhr.setRequestHeader('Authorization', token_type+' '+access_token);
											},
											type: 'POST', 
											dataType: 'json',
											contentType: 'application/json; charset=utf-8',
											url: url2,
											processData: false,
											data: JSON.stringify(data2),
											success: function(msg) {
												// Exibir boleto html
												var payment_id = msg.payment_id;
												window.open('https://api.getnet.com.br/v1/payments/boleto/'+payment_id+'/html', '_blank');
											},
											error: function(request, status, error) {
												//console.log(request.responseText);
												// alert(JSON.stringify(msg));
												alert(request.responseText);
											}
										});
									},
									error: function( msg ) {
										alert('0 Erro na autenticacao do boleto' );
									}
								});
							});			 
						});
					</script> 
				<?php
				}?>
			</div>
			<div id="imp_grade">
				<a href="imp_renovacao_matricula.php" target="_blank"><img src="imagens/grade.png" border="0"></a>
			</div>
		</div>
		<br class="clearfloat"/>  
	</div>
	<?php mysql_close($db);?>
</body>	
</html>