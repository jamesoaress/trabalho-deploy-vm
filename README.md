# Trabalho 2 - Deploy automático em VM

CRUD de filmes em PHP, executado com Docker Compose no Ubuntu Server e atualizado automaticamente pelo GitHub Actions em um runner instalado na própria VM.

| Integrante | RA |
| --- | --- |
| Giovanni Bernardi Rodrigues | 250394 |
| James Soares Silva | 250380 |

Repositório: [jamesoaress/trabalho-deploy-vm](https://github.com/jamesoaress/trabalho-deploy-vm).

## Por onde começar

**Se você está no Windows e ainda vai criar a VM, siga o [guia completo: VirtualBox, Ubuntu, Docker e runner](docs/guia-vm.md).** Ele informa onde executar cada comando, como configurar a rede e como comprovar o deploy.

- [O que o projeto faz](#o-que-o-projeto-faz)
- [Executar com Docker](#executar-com-docker)
- [Como o deploy automático funciona](#como-o-deploy-automático-funciona)
- [Testar e demonstrar](#testar-e-demonstrar)
- [Problemas comuns](#problemas-comuns)
- [Conferência do enunciado e evidências](docs/analise-trabalho2.md)

## O que o projeto faz

Permite **cadastrar, listar, editar e excluir filmes**, com título, diretor e ano. Os três filmes iniciais são inseridos apenas na criação da lista da sessão. Uma exclusão ou edição não é desfeita ao atualizar a página.

| Tecnologia | Função |
| --- | --- |
| PHP 8.2 + Apache | Aplicação e servidor HTTP, imagem `php:8.2-apache` |
| Sessão PHP | Lista individual de filmes de cada navegador |
| Docker + Docker Compose | Construção e execução na VM |
| GitHub Actions | Workflow após push na branch `main` |
| Runner self-hosted Linux x64 | Executa o workflow dentro do Ubuntu da VM |
| VirtualBox + Ubuntu Server | Ambiente local de hospedagem |

Não há banco de dados. O volume Docker conserva os arquivos de sessão ao recriar o container, mas a sessão ainda depende do cookie e pode expirar. Outro navegador ou uma janela anônima recebe uma lista própria. Um catálogo compartilhado exigiria outro armazenamento.

As operações que alteram a lista usam POST com token CSRF, os campos são validados no servidor e a saída HTML é escapada.

## Estrutura

```text
trabalho-deploy-vm/
├── .github/workflows/deploy.yml  # deploy por push ou execução manual
├── docker/php.ini               # configuração das sessões
├── docs/guia-vm.md              # instalação desde a criação da VM
├── docs/analise-trabalho2.md     # requisitos e evidências pendentes
├── tests/smoke.php              # teste HTTP do CRUD
├── index.php                   # entrada da aplicação
├── crud-filmes.php             # CRUD de filmes
├── health.php                  # saúde e commit publicado
├── Dockerfile                  # imagem da aplicação
├── docker-compose.yml          # serviço, porta, volume e healthcheck
└── README.md
```

## Executar com Docker

Pré-requisitos: Git, Docker Engine e plugin Docker Compose instalado e atualizado. O PHP do Windows não é necessário para esta opção.

No terminal do Ubuntu da VM, depois de [instalar o Docker](docs/guia-vm.md#5-instalar-docker-e-compose-na-vm):

```bash
git clone https://github.com/jamesoaress/trabalho-deploy-vm.git
cd trabalho-deploy-vm
docker compose config --quiet
docker compose up -d --build --wait --wait-timeout 120
docker compose ps
curl -fsS http://127.0.0.1:8080/health.php
```

O repositório remoto precisa conter os arquivos desta versão antes de cloná-lo. As alterações locais só chegam ao GitHub depois de commit e push, conforme o guia.

| Onde você acessa | Endereço |
| --- | --- |
| Windows, com NAT e encaminhamento do guia | `http://127.0.0.1:8080/` |
| Terminal da própria VM | `http://127.0.0.1:8080/` |
| Outro computador, usando Bridge na mesma rede | `http://IP_DA_VM:8080/` |

`/crud-filmes.php` também funciona. `/health.php` retorna `status: ok` e `revision`: `local` no build manual ou o SHA do commit no deploy pelo runner.

O Compose usa o nome fixo `trabalho-deploy-vm`; por isso o primeiro teste manual e o runner atualizam o mesmo serviço, mesmo estando em pastas diferentes. Use uma única instalação desse projeto por VM. A porta interna da VM permanece 8080 para corresponder ao workflow e ao encaminhamento do VirtualBox.

### Teste rápido sem Docker no Windows

Se você tem XAMPP no caminho padrão, abra PowerShell na pasta do projeto:

```powershell
& 'C:\xampp\php\php.exe' -S 127.0.0.1:8081 -t .
```

Abra `http://127.0.0.1:8081/`. Para encerrar, use Ctrl+C. Esse teste ajuda a conferir a aplicação; a demonstração do trabalho será na VM com o runner. A porta 8081 evita conflito com o encaminhamento 8080 da VM. O servidor embutido serve a pasta do projeto e deve ser usado somente para esse teste local.

## Como o deploy automático funciona

```text
Alteração no Windows -> commit e push para main -> GitHub Actions
    -> runner Linux na VM -> checkout do commit -> build e validação PHP
    -> atualização do container -> healthcheck -> nova versão no navegador
```

O arquivo [.github/workflows/deploy.yml](.github/workflows/deploy.yml) dispara após qualquer push para `main`. Também permite **Actions > Deploy Automático na VM > Run workflow**, selecionando `main`, para o primeiro teste.

O job exige as labels **`self-hosted`, `linux`, `x64` e `deploy-vm`**. A label personalizada `deploy-vm` é configurada ao registrar o runner, como explicado no guia. O runner trabalha em seu diretório `_work`; você não precisa executar `git pull` ou comandos de deploy na VM após cada alteração.

O workflow verifica acesso ao Docker, constrói a imagem e valida a sintaxe PHP antes de atualizar a aplicação. A implantação aguarda o healthcheck e mostra logs em caso de falha. Ele usa permissão de leitura do repositório, serializa os deploys e não grava o token Git no checkout.

A atualização pode causar uma breve interrupção ao recriar o container. Não há rollback automático. O volume de sessões fica separado do código e continua disponível depois da recriação.

## Testar e demonstrar

1. Cadastre um filme e confira a listagem.
2. Edite um filme padrão e atualize a página: a edição deve permanecer.
3. Exclua outro filme padrão e atualize a página: ele deve continuar excluído.
4. No arquivo `crud-filmes.php`, altere o texto **`Versão para demonstração: 1`** para **`Versão para demonstração: 2`**.
5. Faça commit e push para `main`. Acompanhe a execução na aba Actions e atualize o navegador do Windows com Ctrl+F5.
6. Confira a nova mensagem e o SHA em `/health.php`. Guarde as evidências indicadas em [análise e checklist](docs/analise-trabalho2.md).

Para executar o teste HTTP, mantenha a aplicação aberta e, em outro terminal na pasta do projeto, execute:

```powershell
& 'C:\xampp\php\php.exe' tests/smoke.php http://127.0.0.1:8081
```

Ele usa sua própria sessão e não altera a lista do navegador.

## Comandos úteis na VM

Execute na pasta do clone manual ou no diretório de checkout do runner que contém `docker-compose.yml`:

```bash
docker compose ps
docker compose logs --tail=100
docker compose logs -f
docker compose up -d --build --wait --wait-timeout 120
docker compose down
```

`down` para a aplicação e mantém o volume. `down -v` também apaga as sessões; não use essa opção durante a demonstração.

Para o serviço do runner:

```bash
cd ~/actions-runner
sudo ./svc.sh status
sudo ./svc.sh stop
sudo ./svc.sh start
```

## Problemas comuns

| Sintoma | O que conferir |
| --- | --- |
| `Waiting for a runner` | VM ligada, serviço iniciado e quatro labels do workflow, inclusive `deploy-vm` |
| `permission denied` no Docker | Usuário do runner no grupo `docker`; reconectar SSH e parar/iniciar o serviço |
| `docker: command not found` | Docker instalado dentro do Ubuntu, onde o runner executa |
| `docker compose` ou `--wait` não reconhecido | Instalar/atualizar `docker-compose-plugin` pelo repositório oficial |
| Windows não abre a aplicação | NAT com regra 8080 -> 8080, VM ligada e container saudável |
| SSH recusado | Regra 2222 -> 22, OpenSSH ativo e usuário correto |
| Porta 8080 ocupada na VM | `docker ps` e `sudo ss -ltnp`; verificar serviço antigo antes de pará-lo |
| Porta 8080 ocupada no Windows | Trocar somente a porta do hospedeiro na regra NAT, por exemplo para 8082; acessar `http://127.0.0.1:8082/` |
| Build falhou | Abrir o step com erro em Actions; conferir internet da VM e acesso ao Docker Hub |
| Alteração não aparece | Conferir push para `main`, job concluído, Ctrl+F5 e SHA em `/health.php` |
| Git avisa sobre maiúsculas no Dockerfile | Deixar apenas `Dockerfile` no índice; procedimento no guia |

## Referências

- [Docker Engine no Ubuntu](https://docs.docker.com/engine/install/ubuntu/)
- [Docker Compose up e healthcheck](https://docs.docker.com/reference/cli/docker/compose/up/)
- [Adicionar runner self-hosted](https://docs.github.com/en/actions/how-tos/manage-runners/self-hosted-runners/add-runners)
- [Runner como serviço](https://docs.github.com/en/actions/how-tos/manage-runners/self-hosted-runners/configure-the-application)
- [Rede do VirtualBox 7.2](https://docs.oracle.com/en/virtualization/virtualbox/7.2/user/networkingdetails.html)

Confira o [checklist de entrega](docs/analise-trabalho2.md) após executar o projeto. A existência dos arquivos não comprova, sozinha, que a VM e o runner funcionaram.
