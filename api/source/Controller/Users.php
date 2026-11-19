<?php

namespace Source\Controller;

use Source\Models\Notification;
use Source\Models\Plan;
use Source\Models\School;
use Source\Models\Subject;
use Source\Models\User;
use Source\Core\Connect;
use Source\Support\Upload;

class Users extends Api
{
    private const GLOBAL_ADMIN = 1;
    private const SCHOOL_ADMIN = 2;
    private const TEACHER = 3;
    private const STUDENT = 4;

    public function listAll(array $data): void
    {
        if (!$this->authToken([self::GLOBAL_ADMIN, self::SCHOOL_ADMIN])) {
            $this->call(401, "unauthorized", "Usuário não está autenticado (sem token ou token inválido).", "error")->back();
            return;
        }

        $user = new User();
        if ($this->userAuthTypeId === self::SCHOOL_ADMIN) {
            if ($this->userAuthSchoolId === null) {
                $this->call(403, "forbidden", "Administrador escolar sem escola vinculada.", "error")->back();
                return;
            }
            $users = $user->selectSafeBySchool($this->userAuthSchoolId);
        } else {
            $users = $user->selectSafeAll();
        }
        if ($users === false) {
            $this->call(500, "internal_server_error", "Não foi possível listar os usuários.", "error")->back();
            return;
        }

        $safeUsers = array_map(fn(User $item) => $this->responseData($item), $users);
        $this->call(200, "success", "Lista de usuários", "success")->back($safeUsers);
    }

    public function listById(array $data): void
    {
        if (!$this->authToken(self::GLOBAL_ADMIN)) {
            $this->call(401, "unauthorized", "Usuário não está autenticado (sem token ou token inválido).", "error")->back();
            return;
        }

        if (!$this->validateId($data, "user_id")) {
            $this->call(400, "bad_request", "ID do usuário é obrigatório e deve ser um número inteiro positivo.", "error")->back();
            return;
        }

        $user = new User();
        if (!$user->selectSafeById((int)$data["user_id"])) {
            $this->respondToLookupFailure($user);
            return;
        }

        $this->call(200, "success", "Usuário encontrado", "success")->back($this->responseData($user));
    }

    public function me(array $data): void
    {
        if (!$this->authToken([self::GLOBAL_ADMIN, self::SCHOOL_ADMIN, self::TEACHER, self::STUDENT])) {
            $this->call(401, "unauthorized", "Usuário não está autenticado (sem token ou token inválido).", "error")->back();
            return;
        }

        $user = new User();
        if (!$user->selectSafeById($this->userAuthId)) {
            $this->call(404, "not_found", "Perfil não encontrado.", "error")->back();
            return;
        }

        $this->call(200, "success", "Perfil carregado com sucesso.", "success")->back($this->responseData($user));
    }

    public function register(array $data): void
    {
        $data = $this->normalizeInput($data);
        // O cadastro público só vincula usuários pelo código público da escola.
        unset($data["school_id"], $data["active"]);

        if (!isset($data['password']) || empty($data['password'])) {
            $this->call(400, "bad_request", "A senha é obrigatória.", "error")->back();
            return;
        }

        if (!$this->validateRegister($data, [self::TEACHER, self::STUDENT])) {
            $this->call(400, "bad_request", "Nome, e-mail, senha (mínimo de 6 caracteres) e perfil válido são obrigatórios.", "error")->back();
            return;
        }

        $typeId = isset($data["type_id"]) ? (int)$data["type_id"] : self::STUDENT;
        $schoolCode = isset($data["school_code"]) && is_string($data["school_code"]) ? trim($data["school_code"]) : "";

        if ($typeId === self::TEACHER && $schoolCode === "") {
            $this->call(400, "bad_request", "Professores precisam informar o código da escola para solicitar o vínculo.", "error")->back();
            return;
        }

        if ($schoolCode !== "") {
            $school = new School();
            $schoolId = $school->findActiveIdByCode($schoolCode);
            if ($schoolId === null) {
                $this->call(400, "bad_request", "Código da escola não encontrado.", "error")->back();
                return;
            }
            $data["school_id"] = $schoolId;
        }

        // Professor aguarda a aprovação do administrador escolar; aluno entra ativo.
        $data["active"] = $typeId === self::TEACHER ? 0 : 1;

        if ($typeId === self::STUDENT && isset($data["school_id"]) && !$this->schoolHasVacancy((int)$data["school_id"], self::STUDENT)) {
            $this->call(409, "conflict", "A escola atingiu o limite de alunos do plano contratado.", "error")->back();
            return;
        }

        $subjectNames = [];
        if (isset($data["subjects"]) && $data["subjects"] !== "") {
            if ($typeId !== self::TEACHER || !is_string($data["subjects"])) {
                $this->call(400, "bad_request", "As matérias iniciais só podem ser informadas no cadastro de professor.", "error")->back();
                return;
            }
            $subjectNames = array_values(array_unique(array_filter(array_map(
                static fn(string $name): string => trim($name),
                preg_split('/[,;\r\n]+/', $data["subjects"]) ?: []
            ), static fn(string $name): bool => $name !== "")));
            if (count($subjectNames) > 20 || array_filter($subjectNames, static fn(string $name): bool => strlen($name) > 100)) {
                $this->call(400, "bad_request", "Informe no máximo 20 matérias com até 100 caracteres cada.", "error")->back();
                return;
            }
        }

        $user = $this->makeUser($data, self::STUDENT);
        $connection = Connect::getInstance();
        $connection->beginTransaction();

        if (!$user->insert()) {
            $connection->rollBack();
            if ($user->getErrorMessage() === "Email já cadastrado") {
                $this->call(400, "bad_request", "Email já cadastrado.", "error")->back();
                return;
            }
            error_log("Falha no cadastro de usuário: " . ($user->getErrorMessage() ?? "erro desconhecido"));
            $this->call(500, "internal_server_error", "Não foi possível cadastrar o usuário.", "error")->back();
            return;
        }

        foreach ($subjectNames as $subjectName) {
            $subject = new Subject();
            $existingSubject = $subject->findByNameInSchool($subjectName, (int)$user->getSchoolId());
            if ($subject->getErrorMessage() !== null) {
                $connection->rollBack();
                $this->call(500, "internal_server_error", "Não foi possível consultar as matérias da escola.", "error")->back();
                return;
            }
            if (is_array($existingSubject)) {
                if ((int)$existingSubject["active"] !== 1) {
                    $connection->rollBack();
                    $this->call(400, "bad_request", "Uma das matérias informadas está inativa na escola.", "error")->back();
                    return;
                }
                $subjectId = (int)$existingSubject["id"];
            } else {
                $subject = new Subject(null, (int)$user->getSchoolId(), $subjectName, 1);
                if (!$subject->insert()) {
                    $connection->rollBack();
                    $this->call(500, "internal_server_error", "Não foi possível cadastrar uma das matérias.", "error")->back();
                    return;
                }
                $subjectId = (int)$subject->getId();
            }
            if (!$subject->assignTeacher($subjectId, (int)$user->getId())) {
                $connection->rollBack();
                $this->call(500, "internal_server_error", "Não foi possível associar uma das matérias ao professor.", "error")->back();
                return;
            }
        }
        $connection->commit();

        if (!$user->selectSafeById((int)$user->getId())) {
            $this->call(500, "internal_server_error", "Usuário cadastrado, mas não foi possível consultar os dados.", "error")->back();
            return;
        }

        $message = $typeId === self::TEACHER
            ? "Cadastro enviado. Aguarde a aprovação do administrador da escola para entrar."
            : "Usuário inserido com sucesso";
        $this->call(201, "success", $message, "created")->back($this->responseData($user));
    }

    public function registerAdmin(array $data): void
    {
        if (!$this->authToken(self::GLOBAL_ADMIN)) {
            $this->call(401, "unauthorized", "Usuário não está autenticado (sem token ou token inválido).", "error")->back();
            return;
        }

        $data = $this->normalizeInput($data);

        if (!$this->validateRegister($data, [self::GLOBAL_ADMIN, self::SCHOOL_ADMIN])) {
            $this->call(400, "bad_request", "Nome, e-mail, senha e tipo de administrador válido são obrigatórios.", "error")->back();
            return;
        }

        $user = $this->makeUser($data, self::SCHOOL_ADMIN);

        if (!$user->insert()) {
            if ($user->getErrorMessage() === "Email já cadastrado") {
                $this->call(400, "bad_request", "Email já cadastrado.", "error")->back();
                return;
            }
            error_log("Falha no cadastro administrativo: " . ($user->getErrorMessage() ?? "erro desconhecido"));
            $this->call(500, "internal_server_error", "Não foi possível cadastrar o administrador.", "error")->back();
            return;
        }

        if (!$user->selectSafeById((int)$user->getId())) {
            $this->call(500, "internal_server_error", "Administrador cadastrado, mas não foi possível consultar os dados.", "error")->back();
            return;
        }

        $this->call(201, "success", "Administrador inserido com sucesso", "created")->back($this->responseData($user));
    }

    public function registerSchool(array $data): void
    {
        if (!$this->authToken(self::SCHOOL_ADMIN)) {
            $this->call(401, "unauthorized", "Usuário não está autenticado (sem token ou token inválido).", "error")->back();
            return;
        }

        $data = $this->normalizeInput($data);
        unset($data["school_id"], $data["active"], $data["photo"]);
        if ($this->userAuthSchoolId === null || !$this->validateRegister($data, [self::SCHOOL_ADMIN, self::TEACHER, self::STUDENT])) {
            $this->call(400, "bad_request", "Informe nome, e-mail, senha e um perfil válido para a escola.", "error")->back();
            return;
        }

        $typeId = isset($data["type_id"]) ? (int)$data["type_id"] : self::STUDENT;
        if (!$this->schoolHasVacancy($this->userAuthSchoolId, $typeId)) {
            $this->call(409, "conflict", $this->limitMessage($typeId), "error")->back();
            return;
        }

        $user = $this->makeUser($data, self::STUDENT);
        $user->setSchoolId($this->userAuthSchoolId);
        $user->setActive(1);
        if (!$user->insert()) {
            if ($user->getErrorMessage() === "Email já cadastrado") {
                $this->call(400, "bad_request", "Email já cadastrado.", "error")->back();
                return;
            }
            error_log("Falha no cadastro de usuário escolar: " . ($user->getErrorMessage() ?? "erro desconhecido"));
            $this->call(500, "internal_server_error", "Não foi possível cadastrar o usuário.", "error")->back();
            return;
        }

        if (!$user->selectSafeById((int)$user->getId())) {
            $this->call(500, "internal_server_error", "Usuário cadastrado, mas não foi possível consultar os dados.", "error")->back();
            return;
        }
        $this->call(201, "success", "Usuário inserido com sucesso", "created")->back($this->responseData($user));
    }

    public function auth(array $data): void
    {
        if (!$this->validateCredentials($data)) {
            $this->call(400, "bad_request", "E-mail e senha são obrigatórios. O e-mail deve ser válido.", "error")->back();
            return;
        }

        $user = new User();
        if (!$user->login($data["email"], $data["password"], [self::TEACHER, self::STUDENT])) {
            $this->call(401, "unauthorized", $user->getErrorMessage(), "error")->back();
            return;
        }

        $response = $this->responseData($user);
        $response["token"] = $user->getToken();

        $this->call(200, "success", "Usuário logado com sucesso", "success")->back($response);
    }

    public function authAdmin(array $data): void
    {
        if (!$this->validateCredentials($data)) {
            $this->call(400, "bad_request", "E-mail e senha são obrigatórios. O e-mail deve ser válido.", "error")->back();
            return;
        }

        $user = new User();
        if (!$user->login($data["email"], $data["password"], [self::GLOBAL_ADMIN, self::SCHOOL_ADMIN])) {
            $this->call(401, "unauthorized", $user->getErrorMessage(), "error")->back();
            return;
        }

        $response = $this->responseData($user);
        $response["token"] = $user->getToken();

        $this->call(200, "success", "Usuário logado com sucesso", "success")->back($response);
    }

    public function update(array $data): void
    {
        if (!$this->authToken([self::TEACHER, self::STUDENT])) {
            $this->call(401, "unauthorized", "Usuário não está autenticado (sem token ou token inválido).", "error")->back();
            return;
        }

        $this->updateProfile($data);
    }

    public function updateAdmin(array $data): void
    {
        if (!$this->authToken([self::GLOBAL_ADMIN, self::SCHOOL_ADMIN])) {
            $this->call(401, "unauthorized", "Usuário não está autenticado (sem token ou token inválido).", "error")->back();
            return;
        }

        $this->updateProfile($data);
    }

    public function uploadPhoto(array $data): void
    {
        if (!$this->authToken([self::GLOBAL_ADMIN, self::SCHOOL_ADMIN, self::TEACHER, self::STUDENT])) {
            $this->call(401, "unauthorized", "Usuário não está autenticado (sem token ou token inválido).", "error")->back();
            return;
        }

        if (!isset($_FILES["photo"]) || !is_array($_FILES["photo"])) {
            $this->call(400, "bad_request", "Selecione uma imagem para enviar.", "error")->back();
            return;
        }

        $user = new User();
        if (!$user->selectSafeById($this->userAuthId)) {
            $this->call(404, "not_found", "Perfil não encontrado.", "error")->back();
            return;
        }
        $oldPhoto = $user->getPhoto();

        $uploadDirectory = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . "uploads" . DIRECTORY_SEPARATOR . "users";
        $uploader = new Upload($uploadDirectory);
        try {
            $filename = $uploader->image($_FILES["photo"]);
        } catch (\RuntimeException $exception) {
            $this->call(400, "bad_request", $exception->getMessage(), "error")->back();
            return;
        }

        $photoPath = "uploads/users/" . $filename;
        $photoUrl = $this->photoUrl($photoPath);
        if (!$user->updatePhoto($this->userAuthId, $photoPath)) {
            $savedPath = $uploadDirectory . DIRECTORY_SEPARATOR . $filename;
            if (is_file($savedPath) && !unlink($savedPath)) {
                error_log("Não foi possível remover a foto enviada após falha ao atualizar o perfil.");
            }
            $this->call(500, "internal_server_error", $user->getErrorMessage() ?? "Não foi possível atualizar a foto.", "error")->back();
            return;
        }

        $this->deletePreviousPhoto($oldPhoto, $uploadDirectory);

        $this->call(200, "success", "Foto atualizada com sucesso.", "success")->back([
            "photo" => $photoUrl,
        ]);
    }

    public function updateById(array $data): void
    {
        if (!$this->authToken([self::GLOBAL_ADMIN, self::SCHOOL_ADMIN])) {
            $this->call(401, "unauthorized", "Usuário não está autenticado (sem token ou token inválido).", "error")->back();
            return;
        }

        $data = $this->normalizeInput($data);
        if ($this->userAuthTypeId === self::SCHOOL_ADMIN && (array_key_exists("school_id", $data) || array_key_exists("type_id", $data))) {
            $this->call(403, "forbidden", "Administrador escolar não pode alterar escola ou nível de acesso.", "error")->back();
            return;
        }
        if (!$this->validateId($data, "user_id") || !$this->validateAdminUpdate($data)) {
            $this->call(400, "bad_request", "ID inválido ou campos de usuário inválidos.", "error")->back();
            return;
        }

        if ((int)$data["user_id"] === $this->userAuthId && isset($data["type_id"])) {
            $this->call(400, "bad_request", "Não é permitido alterar o próprio perfil de acesso.", "error")->back();
            return;
        }

        $user = new User();
        if (!$user->selectSafeById((int)$data["user_id"])) {
            $this->respondToLookupFailure($user);
            return;
        }

        if ($this->userAuthTypeId === self::SCHOOL_ADMIN
            && ($this->userAuthSchoolId === null || $user->getSchoolId() !== $this->userAuthSchoolId
                || $user->getTypeId() === self::GLOBAL_ADMIN)) {
            $this->call(404, "not_found", "Usuário não encontrado nesta escola.", "error")->back();
            return;
        }

        // Ativar um usuário (ou mudar o perfil dele) consome uma vaga do plano da escola.
        $newTypeId = isset($data["type_id"]) ? (int)$data["type_id"] : (int)$user->getTypeId();
        $newSchoolId = array_key_exists("school_id", $data)
            ? ($data["school_id"] === "" || $data["school_id"] === null ? null : (int)$data["school_id"])
            : $user->getSchoolId();
        $willBeActive = isset($data["active"]) ? (int)$data["active"] === 1 : $user->getActive() === 1;
        $consumesVacancy = $willBeActive && $newSchoolId !== null
            && ($user->getActive() !== 1 || $newTypeId !== $user->getTypeId() || $newSchoolId !== $user->getSchoolId());
        if ($consumesVacancy && !$this->schoolHasVacancy($newSchoolId, $newTypeId)) {
            $this->call(409, "conflict", $this->limitMessage($newTypeId), "error")->back();
            return;
        }

        $wasPendingTeacher = $user->getTypeId() === self::TEACHER && $user->getActive() !== 1;

        unset($data["user_id"]);
        $scopeSchoolId = $this->userAuthTypeId === self::SCHOOL_ADMIN ? $this->userAuthSchoolId : null;
        if (!$user->updateAdminById((int)$user->getId(), $data, $scopeSchoolId)) {
            if ($user->getErrorMessage() === "Email já cadastrado") {
                $this->call(400, "bad_request", "Email já cadastrado.", "error")->back();
                return;
            }
            error_log("Falha na edição administrativa de usuário: " . ($user->getErrorMessage() ?? "erro desconhecido"));
            $this->call(500, "internal_server_error", "Não foi possível atualizar o usuário.", "error")->back();
            return;
        }

        if (!$user->selectSafeById((int)$user->getId())) {
            $this->call(500, "internal_server_error", "Usuário atualizado, mas não foi possível consultar os dados.", "error")->back();
            return;
        }

        if ($wasPendingTeacher && $user->getTypeId() === self::TEACHER && $user->getActive() === 1) {
            Notification::notify(
                (int)$user->getId(),
                "Vínculo aprovado",
                "Seu vínculo com a escola foi aprovado. Você já pode cadastrar seus horários de atendimento."
            );
        }

        $this->call(200, "success", "Usuário atualizado com sucesso", "success")->back($this->responseData($user));
    }

    public function delete(array $data): void
    {
        if (!$this->authToken([self::GLOBAL_ADMIN, self::SCHOOL_ADMIN])) {
            $this->call(401, "unauthorized", "Usuário não está autenticado (sem token ou token inválido).", "error")->back();
            return;
        }

        if (!$this->validateId($data, "user_id")) {
            $this->call(400, "bad_request", "ID do usuário é obrigatório e deve ser um número inteiro positivo.", "error")->back();
            return;
        }

        if ((int)$data["user_id"] === $this->userAuthId) {
            $this->call(400, "bad_request", "Não é permitido excluir a própria conta.", "error")->back();
            return;
        }

        $user = new User();
        $schoolId = $this->userAuthTypeId === self::SCHOOL_ADMIN ? $this->userAuthSchoolId : null;
        if ($this->userAuthTypeId === self::SCHOOL_ADMIN) {
            if ($schoolId === null || !$user->selectSafeById((int)$data["user_id"])
                || $user->getSchoolId() !== $schoolId || $user->getTypeId() === self::GLOBAL_ADMIN) {
                $this->call(404, "not_found", "Usuário não encontrado nesta escola.", "error")->back();
                return;
            }
        }

        if (!$user->deactivateById((int)$data["user_id"], $schoolId)) {
            if ($user->getErrorMessage() === "Usuário não encontrado ou já inativo.") {
                $this->call(404, "not_found", $user->getErrorMessage(), "error")->back();
                return;
            }
            $this->call(500, "internal_server_error", "Não foi possível excluir o usuário.", "error")->back();
            return;
        }

        $this->call(200, "success", "Usuário excluído com sucesso", "success")->back();
    }

    private function updateProfile(array $data): void
    {
        $name = isset($data["name"]) && is_string($data["name"]) ? trim($data["name"]) : "";
        $email = isset($data["email"]) && is_string($data["email"]) ? trim($data["email"]) : "";
        if ($name === "" || strlen($name) > 150 || strlen($email) > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->call(400, "bad_request", "Informe um nome e um e-mail válido.", "error")->back();
            return;
        }

        $passwordFieldsProvided = array_key_exists("current_password", $data) || array_key_exists("new_password", $data);
        $currentPassword = null;
        $newPassword = null;
        if ($passwordFieldsProvided) {
            $currentPassword = $data["current_password"] ?? null;
            $newPassword = $data["new_password"] ?? null;
            if (!is_string($currentPassword) || $currentPassword === "" ||
                !is_string($newPassword) || strlen($newPassword) < 8) {
                $this->call(400, "bad_request", "Informe a senha atual e uma nova senha com pelo menos 8 caracteres.", "error")->back();
                return;
            }
        }

        $user = new User();
        if (!$user->updateProfile($this->userAuthId, $name, $email, $currentPassword, $newPassword)) {
            $message = $user->getErrorMessage() ?? "Não foi possível atualizar o perfil.";
            $code = match ($message) {
                "Perfil não encontrado." => 404,
                "Este e-mail já está cadastrado." => 409,
                "A senha atual está incorreta.",
                "Informe a senha atual e uma nova senha com pelo menos 8 caracteres." => 400,
                default => 500,
            };
            $status = match ($code) {
                400 => "bad_request",
                404 => "not_found",
                409 => "conflict",
                default => "internal_server_error",
            };
            $this->call($code, $status, $message, "error")->back();
            return;
        }

        $response = $this->responseData($user);
        $response["token"] = $user->getToken();
        $this->call(200, "success", "Perfil atualizado com sucesso.", "success")->back($response);
    }

    private function schoolHasVacancy(int $schoolId, int $typeId): bool
    {
        if (!in_array($typeId, [self::TEACHER, self::STUDENT], true)) {
            return true;
        }

        $school = new School();
        $plan = new Plan();
        if (!$school->selectById($schoolId) || !$plan->selectById((int)$school->getPlanId())) {
            return false;
        }

        $limit = $typeId === self::TEACHER ? (int)$plan->getMaxTeachers() : (int)$plan->getMaxStudents();
        if ($limit === 0) {
            return true; // 0 = sem limite (plano Premium)
        }

        $total = (new User())->countActiveBySchoolAndType($schoolId, $typeId);
        return $total !== null && $total < $limit;
    }

    private function limitMessage(int $typeId): string
    {
        return $typeId === self::TEACHER
            ? "A escola atingiu o limite de professores do plano contratado."
            : "A escola atingiu o limite de alunos do plano contratado.";
    }

    private function makeUser(array $data, int $defaultTypeId): User
    {
        return new User(
            null,
            isset($data["school_id"]) ? (int)$data["school_id"] : null,
            isset($data["type_id"]) ? (int)$data["type_id"] : $defaultTypeId,
            trim($data["name"]),
            trim($data["email"]),
            $data["password"],
            $this->optionalText($data, "phone"),
            null,
            $this->optionalText($data, "registration_number"),
            $this->optionalText($data, "specialization"),
            $this->optionalText($data, "office_room"),
            isset($data["active"]) ? (int)$data["active"] : 1
        );
    }

    private function optionalText(array $data, string $field): ?string
    {
        if (!isset($data[$field]) || !is_string($data[$field]) || trim($data[$field]) === "") {
            return null;
        }
        return trim($data[$field]);
    }

    private function validateRegister(array $data, array $allowedTypes): bool
    {
        if (!$this->validateNameEmail($data) || !isset($data["password"])
            || !is_string($data["password"]) || strlen($data["password"]) < 6) {
            return false;
        }

        $typeId = isset($data["type_id"]) && filter_var($data["type_id"], FILTER_VALIDATE_INT) !== false
            ? (int)$data["type_id"] : (isset($data["type_id"]) ? 0 : null);
        if ($typeId !== null && !in_array($typeId, $allowedTypes, true)) {
            return false;
        }

        if (isset($data["school_id"]) && $data["school_id"] !== ""
            && (!filter_var($data["school_id"], FILTER_VALIDATE_INT) || (int)$data["school_id"] <= 0)) {
            return false;
        }

        if (isset($data["active"]) && !$this->validateActive($data["active"])) {
            return false;
        }

        $lengths = ["phone" => 20, "registration_number" => 50, "specialization" => 100, "office_room" => 100];
        foreach ($lengths as $field => $maxLength) {
            if (isset($data[$field]) && (!is_string($data[$field]) || strlen($data[$field]) > $maxLength)) {
                return false;
            }
        }

        return true;
    }

    private function validateCredentials(array $data): bool
    {
        return isset($data["email"], $data["password"]) &&
            filter_var($data["email"], FILTER_VALIDATE_EMAIL) &&
            trim((string)$data["password"]) !== "";
    }

    private function validateAdminUpdate(array $data): bool
    {
        unset($data["user_id"], $data["user_type"], $data["status"]);
        $allowedFields = [
            "school_id", "type_id", "name", "email", "password", "phone",
            "registration_number", "specialization", "office_room", "active"
        ];

        if (empty($data) || array_diff(array_keys($data), $allowedFields)) {
            return false;
        }
        foreach (["name", "email", "type_id", "active"] as $requiredWhenProvided) {
            if (array_key_exists($requiredWhenProvided, $data) && ($data[$requiredWhenProvided] === null || $data[$requiredWhenProvided] === "")) {
                return false;
            }
        }
        if (isset($data["name"]) && (!is_string($data["name"]) || trim($data["name"]) === "" || strlen($data["name"]) > 150)) {
            return false;
        }
        if (isset($data["email"]) && (!is_string($data["email"]) || strlen($data["email"]) > 150 || !filter_var($data["email"], FILTER_VALIDATE_EMAIL))) {
            return false;
        }
        if (isset($data["password"]) && $data["password"] !== "" && (!is_string($data["password"]) || strlen($data["password"]) < 6)) {
            return false;
        }
        if (isset($data["school_id"]) && $data["school_id"] !== ""
            && (filter_var($data["school_id"], FILTER_VALIDATE_INT) === false || (int)$data["school_id"] <= 0)) {
            return false;
        }
        if (isset($data["type_id"]) && !$this->validateUserType($data["type_id"])) {
            return false;
        }
        if (isset($data["active"]) && !$this->validateActive($data["active"])) {
            return false;
        }

        $lengths = ["phone" => 20, "registration_number" => 50, "specialization" => 100, "office_room" => 100];
        foreach ($lengths as $field => $maxLength) {
            if (isset($data[$field]) && (!is_string($data[$field]) || strlen($data[$field]) > $maxLength)) {
                return false;
            }
        }

        return array_filter(
            $data,
            fn($value, $field) => $field !== "password" || $value !== "",
            ARRAY_FILTER_USE_BOTH
        ) !== [];
    }

    private function validateId(array $data, string $field): bool
    {
        return isset($data[$field])
            && filter_var($data[$field], FILTER_VALIDATE_INT) !== false
            && (int)$data[$field] > 0;
    }

    private function respondToLookupFailure(User $user): void
    {
        if ($user->getErrorMessage() === "Usuário não encontrado.") {
            $this->call(404, "not_found", "Usuário não encontrado.", "error")->back();
            return;
        }

        $this->call(500, "internal_server_error", "Não foi possível consultar o usuário.", "error")->back();
    }

    // Valida somente Nome e Email, mas pode ser alterada para validar mais campos
    private function validateNameEmail(array $data): bool
    {
        return isset($data["name"], $data["email"])
            && is_string($data["name"])
            && is_string($data["email"])
            && trim($data["name"]) !== ""
            && strlen($data["name"]) <= 150
            && strlen($data["email"]) <= 150
            && filter_var(trim($data["email"]), FILTER_VALIDATE_EMAIL);
    }

    private function validateUserType(mixed $typeId): bool
    {
        return filter_var($typeId, FILTER_VALIDATE_INT) !== false
            && in_array((int)$typeId, [self::GLOBAL_ADMIN, self::SCHOOL_ADMIN, self::TEACHER, self::STUDENT], true);
    }

    private function validateActive(mixed $active): bool
    {
        return filter_var($active, FILTER_VALIDATE_INT) !== false && in_array((int)$active, [0, 1], true);
    }

    private function normalizeInput(array $data): array
    {
        if (isset($data["user_type"]) && !isset($data["type_id"])) {
            $data["type_id"] = $data["user_type"];
        }

        if (isset($data["status"]) && !isset($data["active"])) {
            $data["active"] = $data["status"];
        }
        unset($data["user_type"], $data["status"]);

        return $data;
    }

    private function responseData(User $user): array
    {
        return [
            "id" => $user->getId(),
            "school_id" => $user->getSchoolId(),
            "type_id" => $user->getTypeId(),
            "name" => $user->getName(),
            "email" => $user->getEmail(),
            "phone" => $user->getPhone(),
            "photo" => $this->photoUrl($user->getPhoto()),
            "registration_number" => $user->getRegistrationNumber(),
            "specialization" => $user->getSpecialization(),
            "office_room" => $user->getOfficeRoom(),
            "active" => $user->getActive()
        ];
    }

    private function photoUrl(?string $photoPath): ?string
    {
        if ($photoPath === null || trim($photoPath) === "") {
            return null;
        }

        if (filter_var($photoPath, FILTER_VALIDATE_URL)) {
            return $photoPath;
        }

        return url(ltrim($photoPath, "/"));
    }

    private function deletePreviousPhoto(?string $photoUrl, string $uploadDirectory): void
    {
        if ($photoUrl === null || $photoUrl === "") {
            return;
        }

        $photoPath = parse_url($photoUrl, PHP_URL_PATH);
        $uploadsUrlPath = parse_url(url("uploads/users/"), PHP_URL_PATH);
        if (!is_string($photoPath) || !is_string($uploadsUrlPath)) {
            return;
        }

        $normalizedPath = ltrim($photoPath, "/");
        $managedPrefixes = ["uploads/users/", ltrim(rtrim($uploadsUrlPath, "/") . "/", "/")];
        $filename = null;
        foreach ($managedPrefixes as $managedPrefix) {
            if ($managedPrefix !== "" && str_starts_with($normalizedPath, $managedPrefix)) {
                $candidate = substr($normalizedPath, strlen($managedPrefix));
                if (preg_match('/\A[a-f0-9]{32}\.(?:jpg|png|webp)\z/', $candidate)) {
                    $filename = $candidate;
                    break;
                }
            }
        }

        if ($filename === null) {
            return;
        }

        $oldFilePath = $uploadDirectory . DIRECTORY_SEPARATOR . $filename;
        if (is_file($oldFilePath) && !unlink($oldFilePath)) {
            error_log("Não foi possível remover a foto antiga do perfil.");
        }
    }
}
