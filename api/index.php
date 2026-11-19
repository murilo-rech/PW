<?php

error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
// timezone para São Paulo América
date_default_timezone_set('America/Sao_Paulo');

ob_start();

require  __DIR__ . "/vendor/autoload.php";

// os headers abaixo são necessários para permitir o acesso à API por clientes externos ao domínio
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header('Access-Control-Allow-Credentials: true'); // Permitir credenciais

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

use CoffeeCode\Router\Router;

$route = new Router(url("api"),":");

$route->namespace("Source\Controller");

// Usuários
$route->group("/users");
$route->post("/register","Users:register"); // cadastro público de professor ou aluno
$route->post("/login","Users:auth"); // login de professor ou aluno
$route->put("/update","Users:update"); // update do perfil de professor ou aluno
$route->post("/register-admin","Users:registerAdmin"); // cadastro de administrador (admin global)
$route->post("/register-school","Users:registerSchool"); // cadastro de usuário da escola (admin escolar)
$route->post("/login-admin","Users:authAdmin"); // login de administrador global ou escolar
$route->put("/update-admin","Users:updateAdmin"); // update do perfil do administrador autenticado
$route->get("/me","Users:me"); // perfil do usuário autenticado
$route->post("/photo","Users:uploadPhoto"); // upload de foto do usuário autenticado
$route->get("/list/{user_id}","Users:listById"); // select by id (admin global)
$route->get("/list","Users:listAll"); // select all (admin global ou escolar)
$route->put("/admin/{user_id}","Users:updateById"); // update de usuário pelo administrador
$route->delete("/admin/{user_id}","Users:delete"); // exclusão lógica pelo administrador
$route->group(null);

// Endereços do usuário autenticado
$route->group("/addresses");
$route->get("/list/{address_id}","Addresses:listById"); // select by id
$route->get("/list","Addresses:listAll"); // select all
$route->get("/list/paginator/{page}/{per_page}","Addresses:listPaginator"); // select all
$route->post("/","Addresses:insert"); // insert
$route->put("/{address_id}","Addresses:update"); // update
$route->delete("/{address_id}","Addresses:delete"); // delete
$route->group(null);

// FAQs
$route->group("/faqs");
$route->get("/","Faqs:listAll"); // FAQs ativas (área pública)
$route->get("/admin/list","Faqs:listAllAdmin"); // todas as FAQs (admin global)
$route->get("/{faqId}","Faqs:listById");
$route->get("/category/{categoryId}","Faqs:listByCategory");
$route->post("/","Faqs:insert");
$route->put("/{faqId}","Faqs:update");
$route->delete("/{faqId}","Faqs:delete");
$route->group(null);

// Categorias de FAQs
$route->group("/faqs-categories");
$route->get("/","FaqsCategories:listAll");
$route->get("/{categoryId}","FaqsCategories:listById");
$route->post("/","FaqsCategories:insert");
$route->put("/{categoryId}","FaqsCategories:update");
$route->delete("/{categoryId}","FaqsCategories:delete");
$route->group(null);

// Planos
$route->group("/plans");
$route->get("/active","Plans:listActive"); // planos ativos (área pública)
$route->get("/list/{plan_id}","Plans:listById"); // select by id
$route->get("/list","Plans:listAll"); // select all
$route->get("/list/paginator/{page}/{per_page}","Plans:listPaginator"); // select all
$route->post("/","Plans:insert"); // insert
$route->put("/{plan_id}","Plans:update"); // update
$route->delete("/{plan_id}","Plans:delete"); // delete
$route->group(null);

// Escolas
$route->group("/schools");
$route->post("/register","Schools:register"); // cadastro público da escola com o administrador
$route->get("/mine","Schools:listMine"); // escola do administrador escolar
$route->put("/mine/update","Schools:updateMine"); // update da escola do administrador escolar
$route->get("/list/{school_id}","Schools:listById"); // select by id
$route->get("/list","Schools:listAll"); // select all
$route->get("/list/paginator/{page}/{per_page}","Schools:listPaginator"); // select all
$route->post("/","Schools:insert"); // insert
$route->put("/{school_id}","Schools:update"); // update
$route->delete("/{school_id}","Schools:delete"); // delete
$route->group(null);

// Matérias
$route->group("/subjects");
$route->get("/list/{subject_id}","Subjects:listById"); // select by id
$route->get("/list","Subjects:listAll"); // select all
$route->get("/list/paginator/{page}/{per_page}","Subjects:listPaginator"); // select all
$route->post("/","Subjects:insert"); // insert
$route->put("/{subject_id}","Subjects:update"); // update
$route->delete("/{subject_id}","Subjects:delete"); // delete
$route->group(null);

// Agendamentos (entidade principal)
$route->group("/appointments");
$route->get("/list/{appointment_id}","Appointments:listById"); // select by id
$route->get("/list","Appointments:listAll"); // select all
$route->get("/list/paginator/{page}/{per_page}","Appointments:listPaginator"); // select all
$route->post("/","Appointments:insert"); // insert
$route->put("/{appointment_id}","Appointments:update"); // update
$route->put("/{appointment_id}/status","Appointments:updateStatus"); // aprovar, recusar ou concluir
$route->delete("/{appointment_id}","Appointments:delete"); // cancelamento (exclusão lógica)
$route->get("/{appointment_id}/images","Appointments:listImages"); // galeria do agendamento
$route->post("/{appointment_id}/images","Appointments:uploadImages"); // envio de imagens (images[])
$route->delete("/{appointment_id}/images/{image_id}","Appointments:deleteImage"); // remove uma imagem
$route->group(null);

// Disponibilidades
$route->group("/availabilities");
$route->get("/booking","Availabilities:listForBooking"); // horários com vagas para o aluno
$route->get("/list/{availability_id}","Availabilities:listById"); // select by id
$route->get("/list","Availabilities:listAll"); // select all
$route->post("/","Availabilities:insert"); // insert
$route->put("/{availability_id}","Availabilities:update"); // update
$route->delete("/{availability_id}","Availabilities:delete"); // delete
$route->group(null);

// Notificações do usuário autenticado
$route->group("/notifications");
$route->get("/list","Notifications:listAll");
$route->put("/read-all","Notifications:markAllRead");
$route->put("/{notification_id}/read","Notifications:markRead");
$route->group(null);

// Mensagens do formulário de contato
$route->group("/contacts");
$route->get("/list","Contacts:listAll"); // select all (admin global)
$route->post("/","Contacts:insert"); // insert (área pública)
$route->delete("/{contact_id}","Contacts:delete"); // arquivar mensagem (admin global)
$route->group(null);

$route->dispatch();

/** ERROR REDIRECT */
if ($route->error()) {
    header('Content-Type: application/json; charset=UTF-8');
    http_response_code(404);

    echo json_encode([
        "code" => 404,
        "type" => "error",
        "status" => "not_found",
        "message" => "O recurso solicitado não existe."
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

ob_end_flush();
