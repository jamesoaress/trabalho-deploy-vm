# Análise do Trabalho 2

Análise em 29/10/2026, baseada na página 2 do PDF `20261023_113215_Trabalhos2BIm.pdf` e no projeto local `trabalho-deploy-vm`. O enunciado foi usado como critério de avaliação; as instruções de entrega do PDF não autorizam enviar mensagens ou entregar o trabalho por conta do aluno.

## Parecer

O projeto tem a arquitetura adequada para o Trabalho 2: CRUD PHP, Docker Compose e workflow em runner self-hosted. A revisão corrige falhas do CRUD e melhora a configuração e a documentação. **A entrega só estará comprovada depois de executar a aplicação e o workflow na VM e demonstrar a mudança por push.**

Os requisitos de changelog e release são do Trabalho 1 e não foram acrescentados a este projeto.

## Comparação com o enunciado

| Requisito da página 2 | Situação encontrada | Resultado da revisão / próximo passo |
| --- | --- | --- |
| Aplicação CRUD | Quatro operações existiam, mas filmes padrão voltavam após editar/excluir | Correção implementada e teste HTTP de regressão aprovado |
| Criar VM, preferencialmente Ubuntu Server | Configuração da VM não comprovada nesta revisão | Seguir etapas 2 a 4 do guia e registrar evidência |
| Runner instalado, online e executando workflow na VM | Workflow existia; estado online e execução não comprovados | Registrar runner com label `deploy-vm`, instalar serviço e guardar evidências |
| Aplicação na VM, preferencialmente Docker/Compose | Arquivos existentes, com configuração mínima | Build, reinício, volume e healthcheck preparados; execução Docker pendente na VM |
| Atualização automática após push | Workflow para main existia, fazendo down antes do build | Workflow melhora validação, ordem de atualização e diagnóstico; validar em execução real |
| Demonstrar alteração no navegador após push | Havia texto de teste; sem evidência nova verificada | Roteiro antes/depois e SHA em `/health.php` preparados |
| Nome completo dos dois integrantes no README | Somente Giovanni aparecia | Incluídos Giovanni Bernardi Rodrigues, RA 250394, e James Soares Silva, RA 250380 |
| Descrever CRUD e tecnologias | Já constavam, com explicações incompletas | README refeito com operação, armazenamento e limitações |
| Explicar runner e Action | Explicação resumida | Fluxo e guia completo adicionados |
| Orientações para executar/acessar na VM | Não havia criação da VM nem regras NAT detalhadas | Guia inclui criação, instalação Ubuntu, NAT, SSH, Docker, runner e alternativa Bridge |

## Correções e motivos

- **Sementes do CRUD:** a lista inicial era sincronizada em cada requisição e recriava filmes removidos/alterados. Agora ela é carregada uma única vez ao iniciar a lista da sessão.
- **Formulários:** remoção passou de GET para POST; mutações exigem token CSRF. Validação e escape cobrem também atributos HTML e entradas malformadas.
- **Acesso pela raiz:** `index.php` encaminha ao CRUD, permitindo abrir apenas o endereço e a porta.
- **Nome do Dockerfile:** o Git registrava `DockerfIle` e `Dockerfile`, que colidem no Windows. Deve permanecer apenas `Dockerfile` no índice.
- **Arquivos no Apache:** o Dockerfile copia somente a aplicação, sem publicar `.git`, documentação e workflow.
- **Sessões:** diretório explícito e volume nomeado evitam perder imediatamente os arquivos de sessão ao recriar o container. Sessões ainda expiram e são individuais, sem banco de dados.
- **Operação:** `restart: unless-stopped` e nome fixo do projeto Compose mantêm o serviço consistente entre clone manual e workspace do runner.
- **Deploy:** imagem construída e PHP validado antes da troca do container; healthcheck, timeout, permissão mínima, labels explícitas, concorrência serializada e logs de falha.
- **Comprovação:** `/health.php` identifica o commit do build feito pelo workflow e o texto da aplicação permite demonstrar a mudança visual.

Não há promessa de disponibilidade contínua ou rollback automático. A recriação do único container pode causar breve interrupção. Não foram acrescentados banco de dados, login ou requisitos que não constam do trabalho.

## Verificações realizadas

- Leitura integral e inspeção visual da página 2 do PDF.
- Inspeção do código, arquivos Docker/Compose, workflow e estado Git local.
- Sintaxe PHP 8.2 validada.
- 23 verificações HTTP do CRUD aprovadas: criar, listar, editar, excluir, persistência da edição/exclusão dos filmes padrão após recarregar, lista vazia, isolamento de sessões, PRG, CSRF, escape HTML e entradas malformadas.
- Sintaxe de 17 blocos Bash do guia/workflow conferida com `bash -n`; sete links locais da documentação verificados.
- Revisão estática de Compose e workflow; endpoint de saúde e acesso pela raiz testados no PHP local.
- Arquivos revisados aplicados à pasta original no Desktop, com cópia anterior preservada. Entrada duplicada `DockerfIle` removida do índice Git; `git diff --check` aprovado.

**Limites da validação:** Docker não está disponível no terminal Windows usado na revisão. A imagem não foi construída aqui, e o runner não foi registrado nem executado por esta revisão. Não foi criado commit nem feito push ao GitHub. Os resultados do Docker, da rede da VM e do Actions devem ser confirmados com o roteiro prático.

## Checklist para preencher após executar

- [ ] VM Ubuntu criada e acessível por SSH.
- [ ] `docker ps` funciona sem sudo para o usuário do runner.
- [ ] Aplicação abre no navegador e as quatro operações funcionam.
- [ ] Runner aparece Idle com `self-hosted`, `linux`, `x64`, `deploy-vm`.
- [ ] Workflow concluído com sucesso no runner da VM.
- [ ] Texto mudou de versão 1 para versão 2 após commit/push, sem deploy manual.
- [ ] SHA em `/health.php` corresponde ao commit da execução.
- [ ] Capturas/links das evidências guardados.
- [ ] IP/porta real do laboratório registrado no README, quando aplicável.
- [ ] Link completo do repositório enviado ao líder de sala como Trabalho 2.

Registre aqui, depois dos testes:

| Evidência | Preencher com resultado real |
| --- | --- |
| URL de execução bem-sucedida no Actions | Pendente |
| Commit usado na demonstração | Pendente |
| Nome do runner na execução | Pendente |
| Endereço de acesso utilizado | Pendente |
| Local das capturas de antes/depois | Pendente |

## Entrega solicitada no PDF

Modalidade: dupla. Valor: 2,0 pontos. Prazo: **29/10/2026**. Cada dupla envia a URL do repositório ao líder de sala, que reúne Trabalho, Aluno 1, Aluno 2 e URL do repositório na planilha para o professor. Esse envio fica a cargo da dupla.
