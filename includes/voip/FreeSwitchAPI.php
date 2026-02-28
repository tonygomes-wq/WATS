<?php
/**
 * FreeSWITCH API Integration
 * 
 * @package WATS
 * @subpackage VoIP
 */

class FreeSwitchAPI {
    private $host;
    private $port;
    private $password;
    
    public function __construct() {
        $this->host = getenv('FREESWITCH_HOST') ?: 'localhost';
        $this->port = getenv('FREESWITCH_ESL_PORT') ?: 8021;
        $this->password = getenv('FREESWITCH_ESL_PASSWORD') ?: 'ClueCon';
    }
    
    /**
     * Criar usuário no FreeSWITCH
     */
    public function createUser(string $extension, string $password, array $data): bool {
        $xml = $this->generateUserXML($extension, $password, $data);
        $filePath = "/etc/freeswitch/directory/default/{$extension}.xml";
        
        // Salvar arquivo XML
        file_put_contents($filePath, $xml);
        
        // Recarregar configuração
        return $this->executeCommand("reloadxml");
    }
    
    /**
     * Gerar XML de configuração do usuário
     */
    private function generateUserXML(string $extension, string $password, array $data): string {
        $displayName = $data['display_name'] ?? "User {$extension}";
        $vmPassword = $data['voicemail_password'] ?? $extension;
        
        return <<<XML
<include>
  <user id="{$extension}">
    <params>
      <param name="password" value="{$password}"/>
      <param name="vm-password" value="{$vmPassword}"/>
    </params>
    <variables>
      <variable name="toll_allow" value="domestic,international,local"/>
      <variable name="accountcode" value="{$extension}"/>
      <variable name="user_context" value="default"/>
      <variable name="effective_caller_id_name" value="{$displayName}"/>
      <variable name="effective_caller_id_number" value="{$extension}"/>
      <variable name="callgroup" value="wats"/>
    </variables>
  </user>
</include>
XML;
    }
    
    /**
     * Executar comando via ESL (Event Socket Library)
     */
    private function executeCommand(string $command): bool {
        try {
            $socket = fsockopen($this->host, $this->port, $errno, $errstr, 5);
            
            if (!$socket) {
                throw new Exception("Erro ao conectar: {$errstr} ({$errno})");
            }
            
            // Autenticar
            fgets($socket); // Ler banner
            fwrite($socket, "auth {$this->password}\n\n");
            $response = fgets($socket);
            
            if (strpos($response, '+OK') === false) {
                throw new Exception("Falha na autenticação");
            }
            
            // Executar comando
            fwrite($socket, "api {$command}\n\n");
            $result = fgets($socket);
            
            fclose($socket);
            
            return strpos($result, '+OK') !== false;
            
        } catch (Exception $e) {
            error_log("FreeSWITCH API Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obter status de um ramal
     */
    public function getUserStatus(string $extension): ?array {
        $command = "show registrations as json";
        $result = $this->executeCommandWithResult($command);
        
        if (!$result) {
            return null;
        }
        
        $data = json_decode($result, true);
        
        foreach ($data['rows'] ?? [] as $row) {
            if ($row['reg_user'] === $extension) {
                return [
                    'registered' => true,
                    'ip' => $row['network_ip'],
                    'port' => $row['network_port'],
                    'user_agent' => $row['user_agent'] ?? '',
                    'expires' => $row['expires'] ?? 0
                ];
            }
        }
        
        return ['registered' => false];
    }
    
    /**
     * Executar comando e retornar resultado
     */
    private function executeCommandWithResult(string $command): ?string {
        try {
            $socket = fsockopen($this->host, $this->port, $errno, $errstr, 5);
            
            if (!$socket) {
                return null;
            }
            
            // Autenticar
            fgets($socket);
            fwrite($socket, "auth {$this->password}\n\n");
            fgets($socket);
            
            // Executar comando
            fwrite($socket, "api {$command}\n\n");
            
            // Ler resposta completa
            $result = '';
            while (!feof($socket)) {
                $line = fgets($socket);
                if ($line === false) break;
                $result .= $line;
            }
            
            fclose($socket);
            
            return $result;
            
        } catch (Exception $e) {
            error_log("FreeSWITCH API Error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Originar chamada
     */
    public function originateCall(string $from, string $to): ?string {
        $callId = uniqid('call_', true);
        $command = "originate {origination_caller_id_number={$from}}user/{$from} &bridge(user/{$to})";
        
        if ($this->executeCommand($command)) {
            return $callId;
        }
        
        return null;
    }
    
    /**
     * Desligar chamada
     */
    public function hangupCall(string $callId): bool {
        return $this->executeCommand("uuid_kill {$callId}");
    }
    
    /**
     * Colocar chamada em hold
     */
    public function holdCall(string $callId): bool {
        return $this->executeCommand("uuid_hold {$callId}");
    }
    
    /**
     * Retomar chamada
     */
    public function unholdCall(string $callId): bool {
        return $this->executeCommand("uuid_hold off {$callId}");
    }
    
    /**
     * Transferir chamada
     */
    public function transferCall(string $callId, string $destination): bool {
        return $this->executeCommand("uuid_transfer {$callId} {$destination}");
    }
}
