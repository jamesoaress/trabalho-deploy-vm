# Guia: do VirtualBox ao runner funcionando

Este roteiro parte de Windows com Oracle VirtualBox e uma ISO do Ubuntu Server. Use uma VM exclusiva para o trabalho. O caminho principal é **NAT**, com acesso pelo próprio computador. No final há uma alternativa para apresentar em outros computadores do laboratório.

| Local | O que executar |
| --- | --- |
| Windows / PowerShell | Git do projeto, SSH e teste no navegador |
| Janela do VirtualBox | Criar VM, instalar Ubuntu e configurar rede |
| Ubuntu da VM / SSH | Instalar Docker, executar app e configurar runner |
| Site do GitHub | Registrar runner e acompanhar Actions |

O runner será instalado **dentro do Ubuntu**, na mesma VM que executa os containers. O Docker também será instalado ali. Não é necessário instalar Docker Desktop no Windows para seguir este roteiro.

## 1. Publicar os arquivos corrigidos no GitHub

**No Windows, PowerShell**, abra a pasta do projeto original:

```powershell
Set-Location -LiteralPath 'C:\Users\leticia.pereira\Desktop\Trbalho deploy\trabalho-deploy-vm'
git status
git diff
```

Confira se ela contém os novos arquivos `index.php`, `health.php`, `docker/php.ini`, `docs/` e `tests/`, além do README atualizado. Se você está usando o pacote ZIP, extraia-o e copie o conteúdo de `trabalho-deploy-vm` para dentro dessa pasta. Mantenha a pasta `.git` original.

O repositório antigo registrava dois nomes que colidem no Windows. Confirme:

```powershell
git ls-files -- DockerfIle Dockerfile
```

O resultado final deve conter apenas `Dockerfile`. Se o nome errado ainda aparecer, remova somente a entrada dele no índice, preservando o arquivo físico:

```powershell
git rm --cached --ignore-unmatch -- DockerfIle
git add -- Dockerfile
```

Essa remoção pode já estar preparada pela revisão. Não exclua o arquivo pelo Explorer: o Windows pode tratar os dois nomes como o mesmo arquivo. O comportamento de `--cached` é documentado em [git rm](https://git-scm.com/docs/git-rm).

Revise e publique somente os arquivos do projeto:

```powershell
git add -- README.md Dockerfile docker-compose.yml crud-filmes.php index.php health.php .dockerignore .gitignore .gitattributes .github/workflows/deploy.yml docker docs tests
git diff --cached --stat
git status
git commit -m "Corrige CRUD e documenta deploy na VM com runner"
git push origin main
```

Se precisar autenticar o Git, conclua o login solicitado pelo gerenciador de credenciais. Se houver erro de permissão, confirme que está usando uma conta autorizada nesse repositório. Não coloque senhas ou tokens nos arquivos.

Esse push pode deixar um job aguardando runner. Isso é esperado enquanto o runner ainda não estiver instalado. As próximas etapas preparam a VM que vai recebê-lo.

## 2. Criar a máquina virtual

**Na janela do VirtualBox:**

1. Clique em **Novo / New** e dê o nome `ubuntu-devops`.
2. Selecione sua ISO do **Ubuntu Server 64 bits**. Ubuntu Server **24.04 LTS amd64** é uma opção para este roteiro; uma ISO 26.04 LTS também pode ser usada. Se necessário, obtenha a imagem no [site oficial do Ubuntu Server](https://ubuntu.com/download/server).
3. Selecione **Pular instalação desassistida / Skip Unattended Installation**, para acompanhar os passos da instalação. Os nomes das telas variam entre versões.
4. Reserve **4096 MB de RAM e 2 CPUs**, se o computador comportar. Em uma máquina com pouca memória, comece com 2048 MB e 1 CPU; o build pode demorar mais. Deixe recursos disponíveis para o Windows.
5. Crie um disco virtual **VDI de 30 GB**, alocado dinamicamente. Não selecione um disco físico do computador.
6. Conclua o assistente. Antes de iniciar a VM, configure a rede como na etapa seguinte.

Se você já tem uma VM Ubuntu instalada, pode usá-la e seguir para a rede, verificando o usuário e o OpenSSH. Não é necessário reinstalar.

## 3. Configurar NAT e encaminhamento de portas

**Com a VM desligada, no VirtualBox:** abra **Configurações > Rede > Adaptador 1**. Habilite o adaptador, marque cabo conectado e selecione **NAT**. Em **Avançado > Redirecionamento de portas / Port Forwarding**, adicione:

| Nome | Protocolo | IP hospedeiro | Porta hospedeiro | IP convidado | Porta convidado |
| --- | --- | --- | --- | --- | --- |
| SSH | TCP | 127.0.0.1 | 2222 | deixar vazio | 22 |
| Aplicacao | TCP | 127.0.0.1 | 8080 | deixar vazio | 8080 |

Hospedeiro é o Windows; convidado é o Ubuntu. A segunda regra entra na porta 8080 da VM; o Docker encaminha essa porta à 80 do Apache. Com DHCP padrão, deixe o IP convidado vazio.

Isso permite abrir `http://127.0.0.1:8080/` no Windows e usar SSH pela porta 2222. Um IP como `10.0.2.15` pertence à rede NAT interna e não é o endereço usual para abrir a aplicação pelo Windows. Veja [rede NAT e encaminhamento no manual Oracle](https://docs.oracle.com/en/virtualization/virtualbox/7.2/user/networkingdetails.html).

Se a porta 8080 do Windows estiver ocupada, use **8082 na porta hospedeiro** e mantenha **8080 na porta convidado**; nesse caso, abra `http://127.0.0.1:8082/` no Windows. Para SSH, também é possível trocar somente a porta hospedeiro e ajustar o parâmetro `-p`.

## 4. Instalar Ubuntu Server e acessar por SSH

**Na janela da VM**, inicie a instalação:

1. Escolha idioma e teclado.
2. Use Ubuntu Server padrão, rede automática por DHCP, sem proxy se a sua rede não exigir. Mantenha o espelho sugerido.
3. No armazenamento, use o **disco virtual novo de 30 GB dessa VM**. Confira a identificação e a capacidade antes de confirmar a formatação.
4. Crie seu usuário, por exemplo `james`, com senha própria, e o nome do servidor `ubuntu-devops`. Anote o usuário escolhido. A senha digitada no terminal não mostra asteriscos; isso é normal.
5. Marque **Install OpenSSH server**. Não é necessário importar uma chave nesta primeira configuração local.
6. Pule Ubuntu Pro e pacotes opcionais se não precisar deles. Aguarde o término e reinicie.
7. Se pedir para remover a mídia, retire a ISO do drive óptico virtual em **Dispositivos > Drives ópticos**, depois pressione Enter. Não remova o disco virtual.

Faça login no console. Se esqueceu o OpenSSH, instale-o ali:

```bash
sudo apt update
sudo apt install -y openssh-server
sudo systemctl enable --now ssh
```

**No Windows, PowerShell**, substitua `james` se escolheu outro usuário:

```powershell
ssh -p 2222 james@127.0.0.1
```

Na primeira conexão, confira que o destino é sua VM e aceite a chave. Digite a senha do Ubuntu. A partir daqui o prompt desse terminal é do Ubuntu, mesmo estando numa janela do Windows. Veja também o [tutorial oficial de instalação do Ubuntu Server](https://ubuntu.com/tutorials/install-ubuntu-server).

**Dentro do Ubuntu, por SSH:**

```bash
whoami
uname -m
cat /etc/os-release
sudo apt update
sudo apt upgrade -y
sudo apt install -y git curl ca-certificates
```

O esperado para esta configuração é arquitetura `x86_64` (Linux x64). Se for `aarch64`, selecione ARM64 no download do runner e altere `x64` para `ARM64` no workflow.

Se o Ubuntu solicitar reinicialização, execute `sudo reboot`, espere a VM voltar e conecte novamente por SSH. Se o UFW estiver ativo, permita SSH antes de habilitar novas regras:

```bash
sudo ufw status
```

Somente se ele estiver ativo e bloqueando o serviço, use `sudo ufw allow OpenSSH`. O encaminhamento 2222 do Windows chega à porta **22** no Ubuntu.

## 5. Instalar Docker e Compose na VM

**No Ubuntu, por SSH**, use o repositório apt oficial. Os comandos abaixo são para uma instalação nova; se você já usa Docker nessa VM, confira primeiro `docker --version` e `docker compose version` para evitar misturar pacotes.

```bash
sudo apt update
sudo apt install -y ca-certificates curl
sudo install -m 0755 -d /etc/apt/keyrings
sudo curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
sudo chmod a+r /etc/apt/keyrings/docker.asc

sudo tee /etc/apt/sources.list.d/docker.sources > /dev/null <<EOF
Types: deb
URIs: https://download.docker.com/linux/ubuntu
Suites: $(. /etc/os-release && echo "${UBUNTU_CODENAME:-$VERSION_CODENAME}")
Components: stable
Architectures: $(dpkg --print-architecture)
Signed-By: /etc/apt/keyrings/docker.asc
EOF

sudo apt update
sudo apt install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
sudo systemctl enable --now docker
sudo docker run --rm hello-world
sudo usermod -aG docker "$USER"
```

Cole o bloco `sudo tee ...` até o `EOF` final completo. A instalação segue a [documentação oficial do Docker para Ubuntu](https://docs.docker.com/engine/install/ubuntu/).

Depois do `usermod`, encerre a sessão SSH:

```bash
exit
```

**No Windows**, conecte novamente:

```powershell
ssh -p 2222 james@127.0.0.1
```

**De volta ao Ubuntu**, confirme:

```bash
id -nG
docker --version
docker compose version
docker ps
```

O grupo `docker` deve aparecer e `docker ps` deve funcionar **sem sudo**, pois o runner usará esse mesmo usuário. Esse grupo tem privilégios administrativos sobre o Docker; use uma VM dedicada ao trabalho. Veja [pós-instalação do Docker](https://docs.docker.com/engine/install/linux-postinstall/).

## 6. Primeiro teste da aplicação na VM

**No Ubuntu**, após publicar os arquivos corrigidos da etapa 1:

```bash
cd ~
git clone https://github.com/jamesoaress/trabalho-deploy-vm.git
cd ~/trabalho-deploy-vm
docker compose config --quiet
docker compose up -d --build --wait --wait-timeout 120
docker compose ps
curl -fsS http://127.0.0.1:8080/health.php
```

Se o clone já existir, entre na pasta e confira `git status` antes de atualizar com `git pull --ff-only`. O download inicial da imagem PHP pode levar alguns minutos.

O serviço deve aparecer saudável (`healthy`) e o endpoint deve responder com `status: ok`, `revision: local`. O comando aguarda o healthcheck; consulte [docker compose up](https://docs.docker.com/reference/cli/docker/compose/up/).

**No navegador do Windows**, abra `http://127.0.0.1:8080/`. Cadastre, edite e exclua um filme. A página deve mostrar **Versão para demonstração: 1**. Se trocou a porta hospedeiro da regra NAT, use a nova porta na URL do Windows.

Se funcionar no `curl` da VM e não no Windows, confira a regra NAT e a porta hospedeiro. Se falhar dentro da VM, use `docker compose logs --tail=100` e `docker compose ps -a`.

Esse deploy manual serve para validar o ambiente uma vez. O próximo passo automatiza as atualizações.

## 7. Registrar o runner no GitHub

**No site do GitHub**, abra [o repositório](https://github.com/jamesoaress/trabalho-deploy-vm) e siga **Settings > Actions > Runners > New self-hosted runner**. É necessário ser proprietário ou ter permissão administrativa.

1. Selecione **Linux**, arquitetura **x64** para `x86_64`.
2. **No terminal Ubuntu**, volte para sua home com `cd ~`.
3. Execute os comandos da seção **Download** exatamente como aparecem no GitHub. O primeiro cria e acessa `actions-runner`, e os demais baixam, verificam e extraem a versão atual. Não crie outra pasta `actions-runner` dentro dela.
4. Execute o comando `./config.sh ...` da seção **Configure**, incluindo a URL e o token que o GitHub acabou de gerar. Execute como seu usuário comum, sem `sudo`.
5. Nas perguntas interativas, aceite o grupo padrão; dê o nome **ubuntu-devops**; em **additional labels**, digite **deploy-vm**; aceite `_work` como pasta de trabalho.

Use os comandos atuais mostrados pelo GitHub para não copiar uma versão ou checksum antigo. O token de registro expira em uma hora; se expirar, gere outro nessa tela. Ele não é a sua senha nem precisa ser salvo em Secrets, README ou Git. Veja [adicionar runners](https://docs.github.com/en/actions/how-tos/manage-runners/self-hosted-runners/add-runners) e a compatibilidade do [actions/checkout](https://github.com/actions/checkout).

A instalação fica em `~/actions-runner`, **fora do clone da aplicação**. As labels padrão devem incluir `self-hosted`, `linux`, `x64`; a sua label adicional é `deploy-vm`, igual ao workflow.

Se faltarem bibliotecas do runner, execute, de dentro de `~/actions-runner`, `sudo ./bin/installdependencies.sh` e repita a configuração.

Faça um teste em primeiro plano:

```bash
cd ~/actions-runner
./run.sh
```

Espere mensagens como **Connected to GitHub** e **Listening for Jobs**. Se algum job aguardando começar a executar, espere ele terminar antes de interromper. Use Ctrl+C para parar esse teste e siga para instalar o serviço.

O runner precisa de internet de saída por HTTPS para se comunicar com o GitHub. **Não precisa criar uma regra NAT de entrada para o runner.** Veja os [requisitos de comunicação](https://docs.github.com/en/actions/reference/runners/self-hosted-runners).

## 8. Instalar o runner como serviço

**No Ubuntu**, na pasta do runner:

```bash
cd ~/actions-runner
sudo ./svc.sh install "$USER"
sudo ./svc.sh start
sudo ./svc.sh status
```

O status deve indicar serviço ativo. No GitHub, volte a **Settings > Actions > Runners**: ele deve aparecer **Idle** quando estiver disponível, ou **Active** durante um job. O serviço inicia novamente quando o Ubuntu é ligado; a VM e o Windows precisam estar ligados e com acesso à internet. Veja [runner como serviço](https://docs.github.com/en/actions/how-tos/manage-runners/self-hosted-runners/configure-the-application).

Se você adicionou o grupo `docker` depois de instalar/iniciar o serviço, atualize os grupos do processo:

```bash
cd ~/actions-runner
sudo ./svc.sh stop
sudo ./svc.sh start
```

Não use `./run.sh` ao mesmo tempo que o serviço.

## 9. Executar o primeiro workflow

**No GitHub:** abra **Actions > Deploy Automático na VM**. Se o push da etapa 1 já foi processado, abra essa execução. Caso contrário, use **Run workflow**, selecione **main** e confirme a execução.

Abra o job `deploy` e confira o runner **ubuntu-devops**. Os steps devem concluir em verde: checkout, Docker/configuração, build/validação PHP e atualização/saúde.

O runner baixa automaticamente o commit para uma pasta como `~/actions-runner/_work/trabalho-deploy-vm/trabalho-deploy-vm`. Ela é separada do clone manual da etapa 6, mas o nome fixo do Compose faz ambos se referirem ao mesmo serviço e volume.

**No navegador do Windows**, abra a aplicação e depois `http://127.0.0.1:8080/health.php`. Agora `revision` deve mostrar o SHA do commit dessa execução, em vez de `local`.

Depois que o runner assumir o deploy, use o clone manual apenas para consultas de status/logs. Não execute um build manual de código antigo nessa pasta, pois isso pode substituir a versão publicada pelo runner.

Se o job ficar aguardando, confira as quatro labels e o serviço. Se falhar, abra o primeiro step com erro e o diagnóstico final. Não precisa criar um secret SSH: o runner já executa dentro da VM.

## 10. Demonstrar a atualização automática por push

1. No navegador do Windows, registre a mensagem **Versão para demonstração: 1**.
2. No Windows, abra `crud-filmes.php` na pasta original do projeto e troque essa frase para **Versão para demonstração: 2**. Altere o texto visível da página, não apenas a lista inicial de filmes de uma sessão existente.
3. **No PowerShell do Windows**, dentro do projeto:

```powershell
git add -- crud-filmes.php
git commit -m "Demonstra atualizacao automatica na VM"
git push origin main
git rev-parse HEAD
```

4. Acompanhe a nova execução na aba Actions até ficar verde.
5. No navegador, use Ctrl+F5 e confira a mensagem **Versão para demonstração: 2**.
6. Abra `/health.php` e compare `revision` com `git rev-parse HEAD` ou com o commit mostrado em Actions.

**Durante essa demonstração, não execute deploy manual nem git pull na VM.** A mudança deve chegar pelo push e pelo workflow. Isso comprova o objetivo principal do Trabalho 2.

Não é necessário configurar changelog ou release para este trabalho; esses itens pertencem ao Trabalho 1 do PDF.

## 11. Acesso de outro computador no laboratório

Com NAT e IP hospedeiro `127.0.0.1`, a aplicação fica acessível pelo próprio Windows. Para o professor acessar de outra máquina da mesma rede, uma alternativa é **Adaptador em modo Bridge**:

1. Desligue a VM e mude o Adaptador 1 de NAT para **Bridge**, selecionando a interface física conectada à rede.
2. Ligue a VM. No Ubuntu, use `hostname -I` e `ip -4 addr` para identificar o IPv4 da interface da rede do laboratório. Ignore endereços das interfaces Docker.
3. Abra `http://IP_DA_VM:8080/` no outro computador, substituindo pelo IP real, e registre esse endereço no README antes da entrega.
4. Em Bridge, o SSH passa a usar `ssh usuario@IP_DA_VM` na porta 22. As regras NAT deixam de se aplicar.

A rede do laboratório pode bloquear comunicação entre máquinas; teste antes da apresentação. Veja os modos de rede no [manual Oracle](https://docs.oracle.com/en/virtualization/virtualbox/7.2/user/networkingdetails.html).

O Compose publica a porta em todas as interfaces da VM para permitir esse acesso. Portas publicadas pelo Docker podem contornar regras do UFW; não trate `ufw allow/deny` como isolamento suficiente. Use esse ambiente na rede de teste e não crie encaminhamento no roteador da internet. Veja [limitações de firewall do Docker](https://docs.docker.com/engine/network/packet-filtering-firewalls/).

## 12. Guardar evidências e entregar

- VM Ubuntu criada e em funcionamento.
- CRUD com cadastro, listagem, edição e exclusão.
- Runner **Idle** e labels corretas.
- Execução verde no Actions mostrando o job no runner **ubuntu-devops**.
- Antes/depois da mensagem na página, com o commit correspondente.
- Endereço usado para acesso no laboratório, quando aplicável.
- README com os dois integrantes e explicação do fluxo.

Use [a análise e checklist](analise-trabalho2.md) para registrar links e pendências reais. Guarde capturas sem tokens e sem senhas. O GitHub recomenda runners self-hosted em repositórios privados; se o repositório for público, mantenha o runner restrito a esta VM de teste e não acrescente execução de código de pull requests de terceiros. Consulte [segurança de runners](https://docs.github.com/en/actions/how-tos/manage-runners/self-hosted-runners/add-runners).

Envie ao líder de sala o link completo do repositório, identificado como **Trabalho 2**, com os nomes **Giovanni Bernardi Rodrigues** e **James Soares Silva**. O PDF estabelece o prazo de **29/10/2026**.
