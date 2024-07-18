<?php
    class BancoInterDadosFaculdade {

        // Propriedades
        public $conexao;
        public $competencia;

        // Construtor
        public function __construct($conexao, $competencia = null) {
            $this->conexao     = $conexao;
            $this->competencia = $competencia;
        }

        public function buscarPrimeiraParcelaDaRenovacao($periodo_letivo, $matricula) {
            try {                
                $q = "SELECT
                        p.codigo cod_parcela
                        , truncate(p.valor_pagar, 2) valor_pagar
                        , p.data_vencimento
                        , a.cpf_resp_finan
                        , a.nome
                        , (SELECT tl.descricao
                           FROM tipo_logradouro tl
                           WHERE tl.codigo = a.cod_logradouro_resp_finan)logr_cob
                        , a.logradouro_resp_finan descricao_logr_cob
                        , a.cidade_resp_finan
                        , (SELECT u.sigla
                           FROM uf u
                           WHERE u.codigo = a.uf_resp_finan) uf_resp_finan
                        , a.cep_resp_finan
                        , a.email_resp_finan
                        , SUBSTRING(a.tel_cel_resp_finan, 1, 2) ddd_resp_finan
                        , SUBSTRING(a.tel_cel_resp_finan, 4, LENGTH(a.tel_cel_resp_finan)-3) tel_resp_finan 
                        , a.numero_resp_finan
                        , a.complemento_resp_finan
                        , a.bairro_resp_finan
                        , DATE_FORMAT(p.data_vencimento, '%d/%m/%Y') data_vencimento_frm
                        , p.numero
                        , a.nome_resp_finan
                        , pl.data_ini_matricula
                        , pl.data_fim_matricula    
                      FROM 
                        aluno a INNER JOIN inscricao_curso ic ON a.codigo = ic.cod_aluno
                                INNER JOIN inscricao_periodo_letivo ip ON ic.matricula = ip.matricula
                                INNER JOIN periodo_letivo pl on ip.cod_periodo_letivo = pl.codigo
                                INNER JOIN situacao_aluno sa ON ic.matricula = sa.matricula
                                INNER JOIN parcelas_pgto p ON ip.codigo = p.cod_inscricao_periodo_letivo
                      WHERE
                        ip.cod_periodo_letivo = ?
                        AND sa.cod_situacao_atual not in (2, 3, 4, 5, 6, 7, 17)
                        AND ic.matricula = ?
                        AND p.tipo = 1
                        AND p.cod_renegociacao is null
                        AND p.numero = 1
                        AND sa.codigo = (SELECT max(sa1.codigo)
                                         FROM situacao_aluno sa1
                                         WHERE sa1.matricula = ic.matricula)
                      ORDER BY 
                        a.nome";
                $stmt = $this->conexao->prepare($q);
                $stmt->bind_param("is", $periodo_letivo, $matricula);
                $stmt->execute();
                $result = $stmt->get_result();
                $stmt->close();
                return $result->fetch_all(MYSQLI_ASSOC);
            } catch (Exception $e) {
                throw new Exception($e->getMessage());
            }
        }

        private function montarJson($dados) {
          $json = '{';
          $primeiro = true;
      
          foreach ($dados as $chave => $valor) {
              if (!$primeiro) {
                  $json .= ', ';
              }
              $json .= '"' . $chave . '": ';
      
              if (is_array($valor)) {
                  // Se for um array, converte para JSON recursivamente
                  //$json .= '[' . $this->montarJson($valor) . ']';
                  $json .= $this->montarJson($valor);
              } elseif (gettype($valor) == "double" || gettype($valor) == "integer") {
                  // Se for numérico, mantém como está
                  $json .= $valor;
              } else {
                  // Se for string, adiciona aspas duplas e escapa caracteres especiais
                  $json .= '"' . addslashes($valor) . '"';
              }
      
              $primeiro = false;
          }
      
          $json .= '}';
          return $json;
        }

        public function gerarJsonComDadosDoPagador($linha){
            $tipo_logra    = trim($linha["logr_cob"]);
            $logradouro    = trim($linha["descricao_logr_cob"]);
            $endereco      = $tipo_logra . " " . $logradouro;
            
            $dados = array(
              "seuNumero"        => strval($linha["cod_parcela"]),
              "valorNominal"     => round($linha["valor_pagar"], 2),
//              "valorAbatimento"  => 0,
              "dataVencimento"   => $linha["data_vencimento"],
              "numDiasAgenda"    => 35,
//              "atualizarPagador" => false,
              "pagador"          => array(
                "email"       => $linha["email_resp_finan"],
                "ddd"         => strval(intval($linha["ddd_resp_finan"])),
                "telefone"    => strval($linha["tel_resp_finan"]),
                "numero"      => strval($linha["numero_resp_finan"]),
                "complemento" => strval($linha["complemento_resp_finan"]),
                "cpfCnpj"     => strval($linha["cpf_resp_finan"]),
                "tipoPessoa"  => "FISICA",
                "nome"        => $linha["nome"],
                "endereco"    => $endereco,
                "bairro"      => $linha["bairro_resp_finan"],
                "cidade"      => $linha["cidade_resp_finan"],
                "uf"          => $linha["uf_resp_finan"],
                "cep"         => strval($linha["cep_resp_finan"])
              ),
              "multa" => array(
                "valor"  => round((round($linha["valor_pagar"], 2) * 0.02), 2),
                "codigo" => "VALORFIXO"
              ),
              "mora" => array(
                "valor"  => round((round($linha["valor_pagar"], 2) * 0.033)/100, 2),
                "codigo" => "VALORDIA"
              ),
              "mensagem" => array(
                "linha1" => "Vencimento original: " . $linha["data_vencimento_frm"],
                "linha2" => "Valor original: R$ " . $linha["valor_pagar"],
                "linha3" => "Pagamento ref. " . $linha["numero"] . "ª parcela",
                "linha4" => "Não receber após o vencimento. MORA: 0,033% a.d MULTA: 2% a.m"
              )
            );    
    
            return $this->montarJson($dados);
        }
        
        public function baixarParcelasPrisma($item) {
            try {
                $q      = "UPDATE aluno a, inscricao_curso ic, inscricao_periodo_letivo ip, parcelas_pgto pp
                           SET pp.data_pagamento = ?,
                               pp.cod_forma_pagamento = ?,
                               pp.valor_pago = ?,
                               pp.data_credito = ?
                           WHERE pp.codigo = ?
                                 and a.nome = ?
                                 and a.cpf_resp_finan = ?
                                 and a.codigo = ic.cod_aluno
                                 and ic.matricula = ip.matricula
                                 and ip.codigo = pp.cod_inscricao_periodo_letivo";
                $stmt   = $this->conexao->prepare($q);
                $param1 = $item->cobranca->dataSituacao;
                $param2 = 59;
                $param3 = floatval($item->cobranca->valorTotalRecebido);
                $param4 = $item->cobranca->dataSituacao;
                $param5 = intval($item->cobranca->seuNumero);
                $param6 = $item->cobranca->pagador->nome;
                $param7 = $item->cobranca->pagador->cpfCnpj;
                $stmt->bind_param("sidsiss", $param1, $param2, $param3, $param4, $param5, $param6, $param7);
                $stmt->execute();
                $linhas_afetadas = $stmt->affected_rows;
                $stmt->close();
                return $linhas_afetadas;                
            } catch (Exception $e) {
                throw new Exception($e->getMessage());
            }
        }

        public function verificaParcelaAluno($item) {
            try {
                $q = "SELECT
                        ic.matricula
                        , p.codigo cod_parcela
                        , a.nome
                      FROM 
                        aluno a INNER JOIN inscricao_curso ic ON a.codigo = ic.cod_aluno
                                INNER JOIN inscricao_periodo_letivo ip ON ic.matricula = ip.matricula
                                INNER JOIN periodo_letivo pl on ip.cod_periodo_letivo = pl.codigo
                                INNER JOIN situacao_aluno sa ON ic.matricula = sa.matricula
                                INNER JOIN parcelas_pgto p ON ip.codigo = p.cod_inscricao_periodo_letivo
                      WHERE
                            a.nome = ?
                            AND a.cpf_resp_finan = ?
                            AND sa.cod_situacao_atual not in (2, 3, 4, 5, 6, 7, 17)
                            AND now() BETWEEN pl.data_ini_matricula AND pl.data_fim_matricula
                            AND p.tipo = 1
                            AND p.cod_renegociacao is null
                            AND p.numero = 1
                            AND sa.codigo = (SELECT max(sa1.codigo)
                                             FROM situacao_aluno sa1
                                             WHERE sa1.matricula = ic.matricula)
                      ORDER BY 
                        a.nome";
                $stmt   = $this->conexao->prepare($q);
                $param1 = $item->cobranca->pagador->nome;
                $param2 = $item->cobranca->pagador->cpfCnpj;
                $stmt->bind_param("ss", $param1, $param2);
                $stmt->execute();
                $result = $stmt->get_result();
                $stmt->close();
                return $result->fetch_all(MYSQLI_ASSOC);                
            } catch (Exception $e) {
                throw new Exception($e->getMessage());
            }
        }
    }
?>