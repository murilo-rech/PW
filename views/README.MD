# views/

## O que esta pasta guarda

A pasta `views/` concentra tudo que pertence ao frontend do projeto:

- paginas HTML
- estilos CSS
- scripts JavaScript
- recursos visuais usados pelas telas

No MVC, esta e a camada de apresentacao. Ela mostra os dados e dispara interacoes, mas nao deve conter regra de negocio nem acesso direto ao banco.

## Estrutura interna

```text
views/
|-- public/         <- paginas HTML da area publica
|-- app/            <- paginas HTML da area logada
|-- admin/          <- paginas HTML da area administrativa
`-- assets/         <- recursos estaticos
    |-- _common/    <- arquivos compartilhados entre todas as areas
    |   |-- classes/  <- HttpClientBase, servicos da API e entidades (POO classica)
    |   |-- scripts/  <- storage.js, auth-guard.js, profile.js e ui.js
    |   `-- styles/
    |-- public/     <- recursos visuais da area publica
    |-- app/        <- recursos visuais da area logada
    `-- admin/      <- recursos visuais da area administrativa
```

## Areas do sistema

- `public`: visitante sem login
- `app`: usuario autenticado
- `admin`: painel administrativo

## Comunicacao com a API

As telas nao chamam `fetch` diretamente. Cada recurso tem um servico que estende `HttpClientBase` e recebe o token da sessao com `setAuthToken()`, como no projeto do professor:

```javascript
import SessionStorage from "../../_common/scripts/storage.js";
import Faqs from "../../_common/classes/Faqs.js";

const storage = new SessionStorage("admin");
const faqs = new Faqs();
faqs.setAuthToken(storage.getToken());

const response = await faqs.listAllAdmin();
console.log(response.data);
```
