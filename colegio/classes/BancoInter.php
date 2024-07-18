<?php
    class BancoInter {

        // Propriedades locais
        public $SSLCert       = 'C:\wamp64\www\banco_inter\certificados_inter\Inter_API_Certificado.crt';
        public $SSLKey        = 'C:\wamp64\www\banco_inter\certificados_inter\Inter_API_Chave.key';
        
        // Propriedades de Produção
/*
        public $SSLCert       = '/etc/pki/tls/certs/Inter_API_Certificado.crt';
        public $SSLKey        = '/etc/pki/tls/private/Inter_API_Chave.key';
*/        
        public $clientId      = 'cbcf5973-94de-4514-990b-6e49add16844';
        public $clientSecret  = 'ad1daf4a-17f1-4f62-a429-2dfde7467c0b';
        public $contaCorrente = '350416990';
        public $paramCC       = 'x-conta-corrente: 350416990';

        // Construtor
        public function __construct() {
        }

        public function consumirServicosInter($url, $header, $method, $postFields = null) {
            try {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
                curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
                curl_setopt($ch, CURLOPT_SSLCERT, $this->SSLCert);
                curl_setopt($ch, CURLOPT_SSLKEY, $this->SSLKey);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                if ($method === 'POST') {
                    //curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
                }
                $result  = curl_exec($ch);
//                var_dump($result);
                $error   = curl_error($ch);
//                var_dump($error);
                $errorno = curl_errno($ch);
//                var_dump($errorno);
                curl_close ($ch);
                if ($errorno > 0) {
                    throw new Exception($error);
                }                    
                $obj = json_decode($result);
                return $obj;                
            } catch (Exception $e) {
                throw new Exception($e->getMessage());
            }
        }

        public function geraTokenDeAcesso() {
            try {
                $postFields = http_build_query(array(
                    'client_id'     => $this->clientId, 
                    'client_secret' => $this->clientSecret, 
                    'scope'         => 'boleto-cobranca.write boleto-cobranca.read',
                    'grant_type'    => 'client_credentials'
                    ));
                $url    = "https://cdpj.partners.bancointer.com.br/oauth/v2/token";
                $header = array('Content-Type: application/x-www-form-urlencoded');
                return $this->consumirServicosInter($url, $header, "POST", $postFields);
            } catch (Exception $e) {
                throw new Exception($e->getMessage());
            }
        }
        
        public function geraCobrancaNoAppInter($bearerToken, $data) {
            try {
                $bearer     = "Authorization: Bearer " . $bearerToken;
                $postFields = $data;
                $url        = "https://cdpj.partners.bancointer.com.br/cobranca/v3/cobrancas";
                $header     =  array($bearer, $this->paramCC, "Content-Type: application/json");
                return $this->consumirServicosInter($url, $header, "POST", $postFields);
            } catch (Exception $e) {
                throw new Exception($e->getMessage());
            }
        }

        public function visualizarBoletoInter($bearerToken, $codSolicitacao) {
            try {
                $bearer = "Authorization: Bearer " . $bearerToken;
                $url    = "https://cdpj.partners.bancointer.com.br/cobranca/v3/cobrancas/". $codSolicitacao . "/pdf";
                $header =  array($bearer, $this->paramCC, "Content-Type: application/json");
                return $this->consumirServicosInter($url, $header, "GET");
            } catch (Exception $e) {
                throw new Exception($e->getMessage());
            }
        }

        public function pegarBoletosPagosNoAppInter($bearerToken, $parametros) {
            try {
                $bearer      = "Authorization: Bearer " . $bearerToken;
                $queryString = http_build_query($parametros);
                $url         = "https://cdpj.partners.bancointer.com.br/cobranca/v3/cobrancas?". $queryString;
                $header      =  array($bearer, $this->paramCC, "Content-Type: application/json");
                return $this->consumirServicosInter($url, $header, "GET");
            } catch (Exception $e) {
                throw new Exception($e->getMessage());
            }
        }

        public function pegarBoletoUnicoNoAppInter($bearerToken, $parametros) {
            try {
                $bearer      = "Authorization: Bearer " . $bearerToken;
                $queryString = http_build_query($parametros);
                $url         = "https://cdpj.partners.bancointer.com.br/cobranca/v3/cobrancas?". $queryString;
                $header      =  array($bearer, $this->paramCC, "Content-Type: application/json");
                return $this->consumirServicosInter($url, $header, "GET");
            } catch (Exception $e) {
                throw new Exception($e->getMessage());
            }
        }

        public function pegarSumarioDePagamentosNoAppInter($bearerToken, $parametros) {
            try {
                $bearer      = "Authorization: Bearer " . $bearerToken;
                $queryString = http_build_query($parametros);
                $url    = "https://cdpj.partners.bancointer.com.br/cobranca/v3/cobrancas/sumario?". $queryString;
                $header      =  array($bearer, $this->paramCC, "Content-Type: application/json");
                return $this->consumirServicosInter($url, $header, "GET");
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
                    $json .= '[' . $this->montarJson($valor) . ']';
                } elseif (is_numeric($valor) || is_bool($valor)) {
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

        public function cancelarBoletoNoAppInter($bearerToken, $codSolicitacao, $tipo){
            try {
                $bearer    = "Authorization: Bearer " . $bearerToken;
                $motivo    = $tipo === 1 ? "CANCELAMENTO DA MATRICULA" : "BOLETO PAGO ATRAVES DE OUTRA FORMA DE PAGAMENTO";
                $postFields= array(
                    "motivoCancelamento" => $motivo
                );
                $url    = "https://cdpj.partners.bancointer.com.br/cobranca/v3/cobrancas/" . $codSolicitacao . "/cancelar";
                $header =  array($bearer, $this->paramCC, "Content-Type: application/json");
                return $this->consumirServicosInter($url, $header, "POST", $this->montarJson($postFields));
            } catch (Exception $e) {
                throw new Exception($e->getMessage());
            }
        }
    }
?>