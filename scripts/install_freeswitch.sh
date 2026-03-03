#!/bin/bash

###############################################################################
# Script de Instalação Automática do FreeSWITCH para WATS
# Autor: Tony Gomes
# Data: 03/03/2026
# Versão: 1.0.0
###############################################################################

set -e  # Parar em caso de erro

# Cores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Funções auxiliares
print_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[✓]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[!]${NC} $1"
}

print_error() {
    echo -e "${RED}[✗]${NC} $1"
}

# Verificar se está rodando como root
if [ "$EUID" -ne 0 ]; then 
    print_error "Este script precisa ser executado como root"
    echo "Use: sudo bash install_freeswitch.sh"
    exit 1
fi

# Banner
echo "╔════════════════════════════════════════════════════════════╗"
echo "║     Instalação Automática do FreeSWITCH para WATS         ║"
echo "║                    Versão 1.0.0                            ║"
echo "╚════════════════════════════════════════════════════════════╝"
echo ""

# Solicitar informações
print_info "Configuração inicial..."
echo ""

read -p "Digite o domínio do servidor VoIP (ex: voip.macip.com.br): " VOIP_DOMAIN
read -p "Digite o domínio SIP (ex: wats.macip.com.br): " SIP_DOMAIN
read -sp "Digite a senha para ESL (Event Socket Layer): " ESL_PASSWORD
echo ""
read -p "Deseja gerar certificado SSL com Let's Encrypt? (s/n): " GENERATE_SSL

# Validar inputs
if [ -z "$VOIP_DOMAIN" ] || [ -z "$SIP_DOMAIN" ] || [ -z "$ESL_PASSWORD" ]; then
    print_error "Todos os campos são obrigatórios!"
    exit 1
fi

echo ""
print_info "Iniciando instalação..."
echo ""

###############################################################################
# PASSO 1: Atualizar Sistema
###############################################################################
print_info "Passo 1/8: Atualizando sistema..."

apt-get update -qq
apt-get upgrade -y -qq

print_success "Sistema atualizado"

###############################################################################
# PASSO 2: Instalar Dependências
###############################################################################
print_info "Passo 2/8: Instalando dependências..."

apt-get install -y -qq \
    gnupg2 \
    wget \
    curl \
    git \
    build-essential \
    pkg-config \
    uuid-dev \
    zlib1g-dev \
    libjpeg-dev \
    libsqlite3-dev \
    libcurl4-openssl-dev \
    libpcre3-dev \
    libspeexdsp-dev \
    libldns-dev \
    libedit-dev \
    libtiff-dev \
    yasm \
    libopus-dev \
    libsndfile1-dev \
    unzip \
    autoconf \
    automake \
    libtool \
    netcat \
    telnet

print_success "Dependências instaladas"

###############################################################################
# PASSO 3: Instalar FreeSWITCH
###############################################################################
print_info "Passo 3/8: Instalando FreeSWITCH..."

# Adicionar repositório
wget -O - https://files.freeswitch.org/repo/deb/debian-release/fsstretch-archive-keyring.asc 2>/dev/null | apt-key add - 2>/dev/null

# Detectar versão do Ubuntu/Debian
if [ -f /etc/os-release ]; then
    . /etc/os-release
    if [[ "$VERSION_ID" == "22.04" ]] || [[ "$VERSION_ID" == "11" ]]; then
        REPO="bullseye"
    else
        REPO="buster"
    fi
else
    REPO="bullseye"
fi

echo "deb https://files.freeswitch.org/repo/deb/debian-release/ $REPO main" > /etc/apt/sources.list.d/freeswitch.list

# Atualizar e instalar
apt-get update -qq
apt-get install -y freeswitch-meta-all

print_success "FreeSWITCH instalado"

###############################################################################
# PASSO 4: Configurar SSL/TLS
###############################################################################
print_info "Passo 4/8: Configurando SSL/TLS..."

mkdir -p /etc/freeswitch/tls

if [ "$GENERATE_SSL" == "s" ] || [ "$GENERATE_SSL" == "S" ]; then
    # Instalar Certbot
    apt-get install -y certbot
    
    # Parar serviços que usam porta 80
    systemctl stop nginx apache2 2>/dev/null || true
    
    # Gerar certificado
    print_info "Gerando certificado SSL com Let's Encrypt..."
    certbot certonly --standalone -d "$VOIP_DOMAIN" --non-interactive --agree-tos --register-unsafely-without-email
    
    # Combinar certificados
    cat /etc/letsencrypt/live/$VOIP_DOMAIN/fullchain.pem \
        /etc/letsencrypt/live/$VOIP_DOMAIN/privkey.pem \
        > /etc/freeswitch/tls/wss.pem
    
    # Script de renovação
    cat > /etc/letsencrypt/renewal-hooks/post/freeswitch-reload.sh <<EOF
#!/bin/bash
cat /etc/letsencrypt/live/$VOIP_DOMAIN/fullchain.pem \\
    /etc/letsencrypt/live/$VOIP_DOMAIN/privkey.pem \\
    > /etc/freeswitch/tls/wss.pem
chmod 640 /etc/freeswitch/tls/wss.pem
chown freeswitch:freeswitch /etc/freeswitch/tls/wss.pem
systemctl reload freeswitch
EOF
    chmod +x /etc/letsencrypt/renewal-hooks/post/freeswitch-reload.sh
    
    print_success "Certificado SSL gerado"
else
    # Gerar certificado auto-assinado
    print_warning "Gerando certificado auto-assinado (não recomendado para produção)"
    openssl req -x509 -newkey rsa:4096 -keyout /etc/freeswitch/tls/wss.key \
        -out /etc/freeswitch/tls/wss.crt -days 365 -nodes \
        -subj "/CN=$VOIP_DOMAIN"
    cat /etc/freeswitch/tls/wss.crt /etc/freeswitch/tls/wss.key > /etc/freeswitch/tls/wss.pem
fi

# Ajustar permissões
chmod 640 /etc/freeswitch/tls/wss.pem
chown freeswitch:freeswitch /etc/freeswitch/tls/wss.pem

print_success "SSL/TLS configurado"

###############################################################################
# PASSO 5: Configurar FreeSWITCH
###############################################################################
print_info "Passo 5/8: Configurando FreeSWITCH..."

# Backup das configurações originais
cp /etc/freeswitch/vars.xml /etc/freeswitch/vars.xml.bak

# Configurar vars.xml
sed -i "s/<X-PRE-PROCESS cmd=\"set\" data=\"domain=.*\"/<X-PRE-PROCESS cmd=\"set\" data=\"domain=$SIP_DOMAIN\"/" /etc/freeswitch/vars.xml
sed -i "s/<X-PRE-PROCESS cmd=\"set\" data=\"default_password=.*\"/<X-PRE-PROCESS cmd=\"set\" data=\"default_password=$ESL_PASSWORD\"/" /etc/freeswitch/vars.xml

# Configurar mod_verto
cat > /etc/freeswitch/autoload_configs/verto.conf.xml <<EOF
<configuration name="verto.conf" description="HTML5 Verto">
  <settings>
    <param name="debug" value="10"/>
    <param name="enable-fs-events" value="true"/>
  </settings>

  <profiles>
    <profile name="default">
      <param name="bind-local" value="0.0.0.0:8082"/>
      <param name="secure-bind" value="0.0.0.0:8083"/>
      <param name="secure-combined" value="/etc/freeswitch/tls/wss.pem"/>
      <param name="secure-chain" value="/etc/freeswitch/tls/wss.pem"/>
      <param name="userauth" value="true"/>
      <param name="context" value="default"/>
      <param name="dialplan" value="XML"/>
      <param name="timer-name" value="soft"/>
      <param name="rtp-ip" value="\$\${local_ip_v4}"/>
      <param name="ext-rtp-ip" value="\$\${external_rtp_ip}"/>
      <param name="inbound-codec-string" value="OPUS,PCMU,PCMA"/>
      <param name="outbound-codec-string" value="OPUS,PCMU,PCMA"/>
      <param name="apply-candidate-acl" value="localnet.auto"/>
      <param name="apply-candidate-acl" value="wan_v4.auto"/>
    </profile>
  </profiles>
</configuration>
EOF

# Configurar Event Socket Layer
cat > /etc/freeswitch/autoload_configs/event_socket.conf.xml <<EOF
<configuration name="event_socket.conf" description="Socket Client">
  <settings>
    <param name="nat-map" value="false"/>
    <param name="listen-ip" value="127.0.0.1"/>
    <param name="listen-port" value="8021"/>
    <param name="password" value="$ESL_PASSWORD"/>
  </settings>
</configuration>
EOF

# Habilitar mod_verto
if ! grep -q "mod_verto" /etc/freeswitch/autoload_configs/modules.conf.xml; then
    sed -i 's/<\/modules>/<load module="mod_verto"\/>\n<\/modules>/' /etc/freeswitch/autoload_configs/modules.conf.xml
fi

# Criar usuário de teste
cat > /etc/freeswitch/directory/default/1001.xml <<EOF
<include>
  <user id="1001">
    <params>
      <param name="password" value="senha1001"/>
      <param name="vm-password" value="1001"/>
    </params>
    <variables>
      <variable name="toll_allow" value="domestic,international,local"/>
      <variable name="accountcode" value="1001"/>
      <variable name="user_context" value="default"/>
      <variable name="effective_caller_id_name" value="Usuario 1001"/>
      <variable name="effective_caller_id_number" value="1001"/>
    </variables>
  </user>
</include>
EOF

cat > /etc/freeswitch/directory/default/1002.xml <<EOF
<include>
  <user id="1002">
    <params>
      <param name="password" value="senha1002"/>
      <param name="vm-password" value="1002"/>
    </params>
    <variables>
      <variable name="toll_allow" value="domestic,international,local"/>
      <variable name="accountcode" value="1002"/>
      <variable name="user_context" value="default"/>
      <variable name="effective_caller_id_name" value="Usuario 1002"/>
      <variable name="effective_caller_id_number" value="1002"/>
    </variables>
  </user>
</include>
EOF

print_success "FreeSWITCH configurado"

###############################################################################
# PASSO 6: Configurar Firewall
###############################################################################
print_info "Passo 6/8: Configurando firewall..."

# Verificar se UFW está instalado
if command -v ufw &> /dev/null; then
    ufw --force enable
    ufw allow 22/tcp
    ufw allow 5060/tcp
    ufw allow 5060/udp
    ufw allow 5061/tcp
    ufw allow 8021/tcp
    ufw allow 8082/tcp
    ufw allow 8083/tcp
    ufw allow 16384:32768/udp
    print_success "Firewall configurado (UFW)"
else
    print_warning "UFW não encontrado, configure o firewall manualmente"
fi

###############################################################################
# PASSO 7: Criar Serviço Systemd
###############################################################################
print_info "Passo 7/8: Configurando serviço systemd..."

cat > /etc/systemd/system/freeswitch.service <<EOF
[Unit]
Description=FreeSWITCH
After=network.target

[Service]
Type=forking
PIDFile=/var/run/freeswitch/freeswitch.pid
ExecStart=/usr/bin/freeswitch -ncwait -nonat
ExecReload=/usr/bin/freeswitch -reload
ExecStop=/usr/bin/freeswitch -stop
User=freeswitch
Group=freeswitch
Restart=on-failure
RestartSec=10

[Install]
WantedBy=multi-user.target
EOF

systemctl daemon-reload
systemctl enable freeswitch

print_success "Serviço systemd configurado"

###############################################################################
# PASSO 8: Iniciar FreeSWITCH
###############################################################################
print_info "Passo 8/8: Iniciando FreeSWITCH..."

systemctl start freeswitch

# Aguardar inicialização
sleep 5

# Verificar se está rodando
if systemctl is-active --quiet freeswitch; then
    print_success "FreeSWITCH iniciado com sucesso"
else
    print_error "Erro ao iniciar FreeSWITCH"
    print_info "Verifique os logs: journalctl -u freeswitch -n 50"
    exit 1
fi

###############################################################################
# TESTES
###############################################################################
echo ""
print_info "Executando testes..."
echo ""

# Teste 1: Verificar portas
print_info "Teste 1: Verificando portas..."
if netstat -tulpn | grep -q ":8083"; then
    print_success "Porta 8083 (WSS) está aberta"
else
    print_warning "Porta 8083 (WSS) não está aberta"
fi

if netstat -tulpn | grep -q ":8021"; then
    print_success "Porta 8021 (ESL) está aberta"
else
    print_warning "Porta 8021 (ESL) não está aberta"
fi

# Teste 2: Testar ESL
print_info "Teste 2: Testando Event Socket Layer..."
if echo -e "auth $ESL_PASSWORD\nexit\n" | nc localhost 8021 | grep -q "accepted"; then
    print_success "ESL está funcionando"
else
    print_warning "ESL não respondeu corretamente"
fi

###############################################################################
# RESUMO
###############################################################################
echo ""
echo "╔════════════════════════════════════════════════════════════╗"
echo "║              Instalação Concluída com Sucesso!            ║"
echo "╚════════════════════════════════════════════════════════════╝"
echo ""
print_success "FreeSWITCH instalado e configurado"
echo ""
echo "Informações de Configuração:"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "Domínio VoIP:        $VOIP_DOMAIN"
echo "Domínio SIP:         $SIP_DOMAIN"
echo "WebSocket (WS):      ws://$VOIP_DOMAIN:8082"
echo "WebSocket Secure:    wss://$VOIP_DOMAIN:8083"
echo "Event Socket (ESL):  127.0.0.1:8021"
echo "Senha ESL:           $ESL_PASSWORD"
echo ""
echo "Usuários de Teste:"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "Usuário 1:           1001 / senha1001"
echo "Usuário 2:           1002 / senha1002"
echo ""
echo "Comandos Úteis:"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "Status:              systemctl status freeswitch"
echo "Logs:                tail -f /var/log/freeswitch/freeswitch.log"
echo "Console:             fs_cli"
echo "Reiniciar:           systemctl restart freeswitch"
echo ""
echo "Configuração WATS (.env):"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "VOIP_ENABLED=true"
echo "VOIP_PROVIDER=freeswitch"
echo "VOIP_SERVER_HOST=$VOIP_DOMAIN"
echo "VOIP_WSS_PORT=8083"
echo "VOIP_SIP_DOMAIN=$SIP_DOMAIN"
echo "VOIP_ESL_HOST=127.0.0.1"
echo "VOIP_ESL_PORT=8021"
echo "VOIP_ESL_PASSWORD=$ESL_PASSWORD"
echo "VOIP_STUN_SERVER=stun:stun.l.google.com:19302"
echo ""
print_info "Próximo passo: Configurar o WATS para usar o FreeSWITCH"
echo ""
