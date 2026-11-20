# EduSchedule — Projeto didático MVC

## Programação Web II - Técnico em Informática

Micro SaaS para instituições de ensino organizarem os atendimentos entre alunos e professores: a escola contrata um plano, os professores publicam horários e os alunos agendam.

O projeto segue a estrutura e os padrões do repositório do professor (`sorfabiosantos/acme-3am`):

- `PHP` orientado a objetos no backend (API REST em MVC);
- `HTML`, `CSS` e `JavaScript` orientado a objetos (clássico e prototípico) no frontend;
- `MySQL`/`MariaDB` como banco de dados;
- `Composer` com `coffeecode/router` (rotas amigáveis) e `firebase/php-jwt` (autenticação JWT).

## Objetivo

Separar a aplicação em três camadas:

- `Model`: dados, consultas e regras de persistência
- `View`: telas, estilos e interações do usuário
- `Controller`: recebe requisições, valida, organiza o fluxo e devolve a resposta

```text
Usuário -> View -> API (Controller) -> Model -> Banco de Dados
```

## Estrutura de pastas

```text
ProjectPW-main/
|
|-- index.html            <- página inicial (área pública)
|-- README.MD
|-- .gitignore
|
|-- api/                  <- backend MVC em PHP (documentação em api/README.MD)
|   |-- .htaccess         <- rotas amigáveis (mod_rewrite)
|   |-- index.php         <- rotas da API
|   |-- composer.json
|   |-- vendor/
|   `-- source/
|       |-- Config/       <- Config.php (banco, URL e chave JWT)
|       |-- Controller/   <- Api.php + um controller por recurso
|       |-- Core/         <- Connect, Model e JWTToken (do professor)
|       |-- Models/       <- um model por tabela
|       `-- Support/      <- Helpers.php e Upload.php (do professor)
|
|-- data-base/            <- script do banco, migrações e diagrama ER
|-- uploads/              <- fotos e imagens enviadas (fora do Git)
|
`-- views/                <- frontend
    |-- public/           <- HTML da área pública
    |-- app/              <- HTML da área do professor e do aluno
    |-- admin/            <- HTML da área administrativa (escola e global)
    `-- assets/
        |-- _common/
        |   |-- classes/  <- HttpClientBase, serviços e entidades
        |   |-- scripts/  <- storage.js, auth-guard.js, profile.js, ui.js
        |   `-- styles/
        |-- public/
        |-- app/
        `-- admin/
```

## Áreas do sistema

| Área | Quem acessa | Telas |
|---|---|---|
| Pública | Qualquer visitante | Home, Sobre, Contato (envia mensagem), FAQ, Planos, Cadastro, Login, Contratar escola |
| Aplicação | Professor (`3`) e aluno (`4`) | Dashboard, agendar, meus agendamentos (com galeria de imagens), atendimentos, disponibilidades, notificações, perfil |
| Administrativa | Admin escolar (`2`) e admin global (`1`) | Dashboards, usuários, vínculos, relatórios, configurações, escolas, planos, FAQ e categorias, mensagens de contato, perfil |

## Funcionalidades

- **Autenticação:** cadastro, login, logout e sessão por área (`SessionStorage("admin")` para administradores e `SessionStorage()` para professores e alunos). O token vai no cabeçalho `Authorization: Bearer` e o backend valida token, perfil e usuário ativo em toda rota protegida.
- **Vínculo do professor:** o professor se cadastra com o código da escola e fica pendente; o administrador escolar aprova em `views/admin/vinculos.html`.
- **Limites do plano:** a API bloqueia novos alunos/professores quando a escola atinge os limites do plano (`0` = ilimitado).
- **Agendamentos:** o aluno agenda horários com vaga (a API bloqueia horário lotado e duplicado); o professor aprova, recusa e conclui.
- **Notificações automáticas:** aprovação de vínculo, novo pedido, cancelamento, mudança de status e alteração/exclusão de horário.
- **Perfil:** nome, e-mail, troca de senha com a senha atual, endereço e foto (área app e administrativa).
- **Imagens (Etapa 07):** foto do perfil e galeria 1:N dos agendamentos, gravadas com `Source\Support\Upload` em `uploads/`.
- **FAQ:** CRUD de perguntas e categorias, publicar/despublicar e ordenar; a área pública mostra só as publicadas, agrupadas por categoria.
- **Contato:** o formulário grava a mensagem na API; o administrador global lê e arquiva no dashboard global.

## JavaScript orientado a objetos (Etapa 06)

### POO clássica (`class`)

- **Entidades** (`views/assets/_common/classes/`): `User`, `School`, `Plan`, `Subject`, `Availability`, `Appointment`, `Notification`, `Faq`, `FaqCategory`, `Address` e `Contact`. Cada uma tem campos privados (`#id`, `#name`...), construtor, getters e setters com validação e conversão entre o payload da API e o objeto (`fromAPI()`, `toPayload()`, `toJSON()`).
- **Serviços HTTP**: `Users`, `Schools`, `Plans`, `Subjects`, `Availabilities`, `Appointments`, `Notifications`, `Faqs`, `FaqCategories`, `Addresses` e `Contacts` estendem `HttpClientBase` (herança) e reaproveitam `get`, `post`, `postForm`, `put`, `putForm`, `delete`, a URL base e o token (`setAuthToken`). As telas chamam os serviços; nenhuma tela chama `fetch` diretamente e os serviços não manipulam HTML.
- **Encapsulamento:** `HttpClientBase` guarda a URL e os cabeçalhos em campos privados (`#baseUrl`, `#defaultHeaders`); `SessionStorage` guarda as chaves da sessão em `#tokenKey` e `#userKey`.
- **Composição:** a classe `Profile` (`_common/scripts/profile.js`) usa os serviços `Users` e `Addresses` e a entidade `Address`; os serviços compõem as entidades ao converter as respostas.

### POO prototípica (`Object.create`)

- **Toast** (`_common/scripts/ui.js`): `ToastPrototype` contém o método `show()`; `publicToast` e `adminToast` são dois objetos criados com `Object.create(ToastPrototype)` e compartilham o mesmo `show()` pelo protótipo (o método não é copiado para cada objeto). Ele aceita a resposta da API com ou sem `data` e usa as cores `success` (verde), `warning` (amarelo) e `error` (vermelho).
- **FormController** (`_common/classes/FormController.js`): `FormControllerPrototype` contém `read()`, `setLoading()` e `clear()`; `createFormController(form)` cria um objeto por formulário com `Object.create`, e todos compartilham esses métodos.

## Configurar no XAMPP

1. Coloque o projeto em `C:\xampp\htdocs\ProjectPW-main` e inicie **Apache** e **MySQL**. O Apache precisa do `mod_rewrite` para aplicar `api/.htaccess`.
2. Banco novo: importe `data-base/EduScheduleC.sql`. **Esse arquivo apaga e recria as tabelas.** Banco existente: execute apenas as migrações de `data-base/migrations/`, em ordem de data (veja `data-base/README.MD`).
3. Confira `api/source/Config/Config.php`: banco `db-acme-tarde` em `localhost:3306`, URL `http://localhost/ProjectPW-main` e a chave `JWT_SECRET_KEY` (gere uma chave própria em produção).
4. Requer PHP 8.1 ou superior com PDO MySQL e `fileinfo`. Se `api/vendor/` não existir, execute `composer install` dentro de `api/`.
5. No `php.ini`, use `upload_max_filesize` e `post_max_size` de pelo menos 5 MB (limite da classe `Upload`).
6. Abra `http://localhost/ProjectPW-main/`. A API fica em `http://localhost/ProjectPW-main/api`.

## Roteiro rápido de teste

1. **Área pública:** envie uma mensagem em Contato, veja os planos e as FAQs, cadastre um aluno e um professor com o código `IFSUL-PEL-2026`.
2. **Admin escolar** (`admin@ifsul.edu.br`): aprove o professor em Vínculos, confira alunos/professores, relatórios, configurações e o perfil.
3. **Professor:** em Disponibilidades, associe uma matéria e publique um horário.
4. **Aluno:** agende o horário com imagens, adicione/remova imagens em Meus Agendamentos e confira as notificações.
5. **Professor:** aprove o atendimento; o aluno recebe a notificação.
6. **Admin global** (`global@eduschedule.test`): escolas, planos, usuários, administradores, FAQ e categorias, mensagens de contato no dashboard.

Use contas e dados descartáveis durante os testes.
