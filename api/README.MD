# API do EduSchedule

Essa é a documentação do web service do nosso projeto. A API foi feita em PHP seguindo a mesma estrutura do projeto do professor (acme-3am): rotas com o `coffeecode/router`, controllers que estendem a classe `Api`, models que estendem `Model` e login com JWT usando a classe `JWTToken`. O frontend e o app mobile usam essas mesmas rotas.

## Como rodar

1. Colocar o projeto em `C:\xampp\htdocs\ProjectPW-main` e ligar o Apache e o MySQL no XAMPP.
2. Importar o banco `data-base/EduScheduleC.sql` (se o banco já existir, rodar as migrações da pasta `data-base/migrations`, incluindo `20261126_admin_accounts.sql` para atualizar as contas administrativas iniciais).
3. Se não tiver a pasta `api/vendor`, rodar `composer install` dentro da pasta `api`.

A URL base é:

```
http://localhost/ProjectPW-main/api
```

As rotas só funcionam se o Apache estiver lendo o `api/.htaccess` (mod_rewrite ligado).

## Como enviar os dados

Igual no projeto do professor, a API recebe os dados como formulário e não como JSON:

- `POST` → form-data (no front usamos o `postForm()` do `HttpClientBase`)
- `PUT` → x-www-form-urlencoded (`putForm()`)
- upload de imagem → multipart com `FormData`

O que está entre chaves na rota, tipo `{faqId}`, é o id que vai na própria URL.

## Login e token

Depois do login a API devolve um token JWT em `data.token`. Nas rotas que precisam de login é só mandar ele no cabeçalho:

```
Authorization: Bearer SEU_TOKEN
```

O token vale 90 minutos. Em toda rota protegida o backend confere o token, o tipo de usuário e se o usuário ainda está ativo.

Tipos de usuário (`type_id`):

| type_id | Perfil | Faz login em |
|---|---|---|
| 1 | Administrador global | `/users/login-admin` |
| 2 | Administrador da escola | `/users/login-admin` |
| 3 | Professor | `/users/login` |
| 4 | Aluno | `/users/login` |

## Formato das respostas

Todas as respostas seguem o mesmo padrão. Quando dá certo:

```json
{
  "code": 200,
  "type": "success",
  "status": "success",
  "message": "Usuário logado com sucesso",
  "data": { }
}
```

Quando dá erro vem `type: "error"` e não vem `data`:

```json
{
  "code": 401,
  "type": "error",
  "status": "unauthorized",
  "message": "Usuário não está autenticado (sem token ou token inválido)."
}
```

Códigos que a API usa:

- `200` deu certo
- `201` registro criado
- `400` faltou campo ou algum dado está inválido
- `401` sem token, token inválido/vencido ou perfil sem permissão
- `403` está logado mas não pode mexer naquele registro
- `404` rota ou registro não encontrado
- `409` conflito (e-mail ou código repetido, horário lotado, limite do plano)
- `500` erro no banco

## Rotas

Na coluna "Quem pode", "público" quer dizer que não precisa de token.

### Usuários

| Método | Rota | Quem pode | Códigos |
|---|---|---|---|
| POST | `/users/register` | público | 201, 400, 409 |
| POST | `/users/login` | público (professor e aluno) | 200, 400, 401 |
| POST | `/users/login-admin` | público (administradores) | 200, 400, 401 |
| GET | `/users/me` | qualquer usuário logado | 200, 401, 404 |
| PUT | `/users/update` | professor e aluno | 200, 400, 401, 409 |
| PUT | `/users/update-admin` | administradores | 200, 400, 401, 409 |
| POST | `/users/photo` | qualquer usuário logado | 200, 400, 401 |
| GET | `/users/list` | admin global (todos) e admin escolar (só da escola dele) | 200, 401 |
| GET | `/users/list/{user_id}` | admin global | 200, 400, 401, 404 |
| POST | `/users/register-admin` | admin global | 201, 400, 401 |
| POST | `/users/register-school` | admin escolar | 201, 400, 401, 409 |
| PUT | `/users/admin/{user_id}` | admin global e admin escolar | 200, 400, 401, 403, 404, 409 |
| DELETE | `/users/admin/{user_id}` | admin global e admin escolar | 200, 400, 401, 404 |

- **Cadastro:** `name`, `email`, `password` (mínimo 6) e `type_id` (3 ou 4). Professor tem que mandar `school_code` (o código da escola) e pode mandar `specialization` e `subjects` (matérias separadas por vírgula). O professor fica pendente até o admin da escola aprovar; o aluno já entra ativo.
- **Login:** `email` e `password`.
- **Atualizar perfil:** `name` e `email`. Para trocar a senha manda também `current_password` e `new_password` (mínimo 8). Sem a senha atual a senha não muda.
- **Foto:** campo `photo` (jpg, png ou webp até 5 MB). A resposta traz a URL da foto nova.
- **Editar usuário (admin):** pode mandar `name`, `email`, `password`, `phone`, `active` etc. Mandar `active=1` num professor pendente é o que aprova o vínculo. O admin escolar não pode trocar o tipo nem a escola do usuário.
- O `DELETE` não apaga de verdade, só deixa o usuário inativo (`active = 0`).

### Endereços

Todas precisam de login e cada um só vê os próprios endereços. Campos: `street` e `number`.

| Método | Rota | Códigos |
|---|---|---|
| GET | `/addresses/list` | 200, 401 |
| GET | `/addresses/list/{address_id}` | 200, 400, 401, 403, 404 |
| GET | `/addresses/list/paginator/{page}/{per_page}` | 200, 400, 401 |
| POST | `/addresses` | 201, 400, 401 |
| PUT | `/addresses/{address_id}` | 200, 400, 401, 403, 404 |
| DELETE | `/addresses/{address_id}` | 200, 400, 401, 403, 404 |

### FAQs e categorias

As rotas são as mesmas do projeto do professor. Só o admin global (tipo 1) cadastra, edita e exclui.

| Método | Rota | Quem pode | Códigos |
|---|---|---|---|
| GET | `/faqs` | público (só as publicadas) | 200 |
| GET | `/faqs/admin/list` | admin global (todas) | 200, 401 |
| GET | `/faqs/{faqId}` | público (despublicada só pro admin) | 200, 400, 404 |
| GET | `/faqs/category/{categoryId}` | público | 200, 400 |
| POST | `/faqs` | admin global | 201, 400, 401 |
| PUT | `/faqs/{faqId}` | admin global | 200, 400, 401, 404 |
| DELETE | `/faqs/{faqId}` | admin global | 200, 400, 401, 404 |
| GET | `/faqs-categories` | público | 200 |
| GET | `/faqs-categories/{categoryId}` | público | 200, 400, 404 |
| POST | `/faqs-categories` | admin global | 201, 400, 401 |
| PUT | `/faqs-categories/{categoryId}` | admin global | 200, 400, 401, 404 |
| DELETE | `/faqs-categories/{categoryId}` | admin global | 200, 400, 401, 404 |

Campos da FAQ: `faqs_category_id`, `question`, `answer`, `active` (1 publicada, 0 despublicada) e `sort_order` (ordem). No `PUT` dá pra mandar só o que mudou, por exemplo só `active=0` pra despublicar. Categoria só tem `name`.

### Planos e escolas

| Método | Rota | Quem pode | Códigos |
|---|---|---|---|
| GET | `/plans/active` | público | 200 |
| GET | `/plans/list` e `/plans/list/{plan_id}` | admin global | 200, 400, 401, 404 |
| POST, PUT, DELETE | `/plans` e `/plans/{plan_id}` | admin global | 200/201, 400, 401, 404 |
| POST | `/schools/register` | público | 201, 400, 409 |
| GET | `/schools/mine` | admin escolar | 200, 401 |
| PUT | `/schools/mine/update` | admin escolar | 200, 400, 401, 409 |
| GET | `/schools/list` e `/schools/list/{school_id}` | admin global (e escolar só a dele) | 200, 400, 401, 403, 404 |
| POST, PUT, DELETE | `/schools` e `/schools/{school_id}` | admin global | 200/201, 400, 401, 404, 409 |

- **Plano:** `name`, `price`, `max_students`, `max_teachers` (0 = sem limite), `description` e `active`. A API não deixa passar do limite de alunos e professores do plano da escola.
- **Cadastro da escola (página Contratar):** `plan_id`, `school_name`, `school_code`, `school_cnpj` (14 números), `school_phone`, `admin_name`, `admin_email`, `admin_password` e `admin_password_confirm`. Cria a escola e o admin dela juntos.

### Matérias e disponibilidades

| Método | Rota | Quem pode | Códigos |
|---|---|---|---|
| GET | `/subjects/list` e `/subjects/list/{subject_id}` | qualquer usuário logado | 200, 400, 401, 403, 404 |
| POST | `/subjects` | admin escolar e professor | 200/201, 400, 401, 403 |
| PUT, DELETE | `/subjects/{subject_id}` | admin escolar e professor | 200, 400, 401, 403, 404 |
| GET | `/availabilities/booking` | aluno (horários com vaga) | 200, 401 |
| GET | `/availabilities/list` e `/availabilities/list/{availability_id}` | qualquer usuário logado | 200, 400, 401, 403, 404 |
| POST | `/availabilities` | professor | 201, 400, 401, 403 |
| PUT, DELETE | `/availabilities/{availability_id}` | professor dono do horário | 200, 400, 401, 403, 404 |

- **Matéria:** só `name` (o admin escolar também pode mandar `active`).
- **Horário:** `subject_id`, `modality` (1 online, 2 presencial), `weekday` (1 a 7), `start_time`, `end_time`, `max_students` (1 a 20) e `meeting_link` (se for online) ou `location` (se for presencial).
- Se o professor editar um horário os alunos inscritos recebem notificação. Se excluir, os agendamentos daquele horário são cancelados e os alunos também são avisados.

### Agendamentos (entidade principal)

| Método | Rota | Quem pode | Códigos |
|---|---|---|---|
| GET | `/appointments/list` | qualquer usuário logado | 200, 401 |
| GET | `/appointments/list/{appointment_id}` | quem tem acesso ao agendamento | 200, 400, 401, 403, 404 |
| GET | `/appointments/list/paginator/{page}/{per_page}` | qualquer usuário logado | 200, 400, 401 |
| POST | `/appointments` | aluno | 201, 400, 401, 404, 409 |
| PUT | `/appointments/{appointment_id}` | aluno dono (só se estiver pendente) | 200, 400, 401, 403, 404, 409 |
| PUT | `/appointments/{appointment_id}/status` | professor e administradores | 200, 400, 401, 403, 404 |
| DELETE | `/appointments/{appointment_id}` | aluno dono (cancela) | 200, 400, 401, 403, 404 |
| GET | `/appointments/{appointment_id}/images` | quem tem acesso ao agendamento | 200, 400, 401, 403, 404 |
| POST | `/appointments/{appointment_id}/images` | aluno dono | 201, 400, 401, 403, 404 |
| DELETE | `/appointments/{appointment_id}/images/{image_id}` | aluno dono ou admin global | 200, 400, 401, 403, 404 |

- Na listagem cada um vê só o que é seu: o aluno vê os dele, o professor os dos horários dele, o admin escolar os da escola e o admin global todos.
- **Agendar:** `availability_id` e `notes` (opcional). A API não deixa agendar em horário lotado nem agendar duas vezes o mesmo horário (409).
- **Status:** `status` = `approved`, `rejected` ou `completed`. Um pendente pode ser aprovado ou recusado; um aprovado pode ser concluído.
- **Imagens:** campo `images[]`, de 1 a 20 imagens por vez (jpg, png ou webp até 5 MB cada). Elas são salvas com a classe `Upload` do professor na pasta `uploads/appointments` e o banco guarda só o caminho. Se der erro no banco o arquivo é apagado, e na exclusão primeiro sai o registro e depois o arquivo.

### Notificações

Cada usuário só vê as dele. Elas são criadas sozinhas pela API (aprovação do vínculo, pedido novo, cancelamento, mudança de status e mudança de horário).

| Método | Rota | Códigos |
|---|---|---|
| GET | `/notifications/list` | 200, 401 |
| PUT | `/notifications/{notification_id}/read` | 200, 400, 401, 404 |
| PUT | `/notifications/read-all` | 200, 401 |

### Contato

| Método | Rota | Quem pode | Códigos |
|---|---|---|---|
| POST | `/contacts` | público | 201, 400 |
| GET | `/contacts/list` | admin global | 200, 401 |
| DELETE | `/contacts/{contact_id}` | admin global (arquiva) | 200, 400, 401, 404 |

Campos: `name`, `email`, `subject` (`demo`, `planos`, `suporte`, `parceria` ou `outro`) e `message`.

## Exemplos

Login de aluno:

```bash
curl -X POST "http://localhost/ProjectPW-main/api/users/login" \
  -d "email=aluno@escola.edu.br" -d "password=senha123"
```

Resposta (200):

```json
{
  "code": 200,
  "type": "success",
  "status": "success",
  "message": "Usuário logado com sucesso",
  "data": {
    "id": 10,
    "school_id": 1,
    "type_id": 4,
    "name": "Aluno Exemplo",
    "email": "aluno@escola.edu.br",
    "photo": null,
    "active": 1,
    "token": "eyJ0eXAiOiJKV1Qi..."
  }
}
```

Agendar um horário e mandar duas imagens:

```bash
curl -X POST "http://localhost/ProjectPW-main/api/appointments" \
  -H "Authorization: Bearer SEU_TOKEN" -d "availability_id=8" -d "notes=Dúvida na prova"

curl -X POST "http://localhost/ProjectPW-main/api/appointments/12/images" \
  -H "Authorization: Bearer SEU_TOKEN" -F "images[]=@foto1.png" -F "images[]=@foto2.png"
```

Professor aprovando o agendamento:

```bash
curl -X PUT "http://localhost/ProjectPW-main/api/appointments/12/status" \
  -H "Authorization: Bearer TOKEN_DO_PROFESSOR" -d "status=approved"
```

Tentando agendar num horário que já lotou (409):

```json
{
  "code": 409,
  "type": "error",
  "status": "conflict",
  "message": "Este horário não possui mais vagas disponíveis."
}
```

## Outras informações

- Também tem paginação em `/plans/list/paginator/{page}/{per_page}` (admin global), `/schools/list/paginator/{page}/{per_page}` (admin global) e `/subjects/list/paginator/{page}/{per_page}` (qualquer usuário logado), além das de endereços e agendamentos. Elas devolvem `page`, `perPage`, `total` e `data` (códigos 200, 400 e 401).
- Quase tudo usa exclusão lógica (`active = 0`). A única coisa que é apagada de verdade é uma imagem da galeria.
- As fotos ficam em `uploads/users` e as imagens dos agendamentos em `uploads/appointments`. Essa pasta não vai pro GitHub (está no `.gitignore`, igual no projeto do professor).
- No `php.ini`, o `upload_max_filesize` e o `post_max_size` precisam ser de pelo menos 5 MB.
