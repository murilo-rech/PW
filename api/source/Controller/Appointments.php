<?php

namespace Source\Controller;

use Source\Controller\Api;
use Source\Core\Connect;
use Source\Models\Appointment;
use Source\Models\AppointmentImage;
use Source\Models\Availability;
use Source\Models\Notification;
use Source\Support\Upload;

class Appointments extends Api
{
    private const GLOBAL_ADMIN = 1;
    private const SCHOOL_ADMIN = 2;
    private const TEACHER = 3;
    private const STUDENT = 4;
    private const MAX_IMAGES_PER_REQUEST = 20;

    public function listById(array $data): void
    {
        if (!$this->authToken([self::GLOBAL_ADMIN, self::SCHOOL_ADMIN, self::TEACHER, self::STUDENT])) {
            $this->call(401, "unauthorized", "Usuário não está autenticado (sem token ou token inválido).", "error")->back();
            return;
        }

        if (!$this->validateId($data, "appointment_id")) {
            $this->call(400, "bad_request", "ID do agendamento é obrigatório e deve ser um número inteiro", "error")->back();
            return;
        }

        $appointment = new Appointment();
        if (!$appointment->selectById((int)$data["appointment_id"])) {
            $this->call(404, "not_found", "Agendamento não encontrado", "error")->back();
            return;
        }

        if (!$this->canAccess((int)$data["appointment_id"], $appointment)) {
            $this->call(403, "forbidden", "Usuário sem permissão para acessar este agendamento.", "error")->back();
            return;
        }

        $this->call(200, "success", "Agendamento encontrado", "success")->back($this->responseData($appointment));
    }

    public function listAll(array $data): void
    {
        if (!$this->authToken([self::GLOBAL_ADMIN, self::SCHOOL_ADMIN, self::TEACHER, self::STUDENT])) {
            $this->call(401, "unauthorized", "Usuário não está autenticado (sem token ou token inválido).", "error")->back();
            return;
        }

        $appointment = new Appointment();
        $rows = $appointment->selectAll($this->permissionFilters());
        $this->call(200, "success", "Lista de Agendamentos", "success")->back($this->formatRows($rows));
    }

    public function listPaginator(array $data): void
    {
        if (!$this->authToken([self::GLOBAL_ADMIN, self::SCHOOL_ADMIN, self::TEACHER, self::STUDENT])) {
            $this->call(401, "unauthorized", "Usuário não está autenticado (sem token ou token inválido).", "error")->back();
            return;
        }

        if (!$this->validateId($data, "page") || !$this->validateId($data, "per_page")) {
            $this->call(400, "bad_request", "Os campos page e per_page são obrigatórios e devem ser números inteiros", "error")->back();
            return;
        }

        $appointment = new Appointment();
        $response = $appointment->selectPaginator(
            (int)$data["page"],
            (int)$data["per_page"],
            $this->permissionFilters(),
            "id",
            "ASC"
        );

        if (isset($response["data"]) && is_array($response["data"])) {
            $response["data"] = $this->formatRows($response["data"]);
        }

        $this->call(200, "success", "Lista de Agendamentos com Paginação", "success")->back($response);
    }

    public function insert(array $data): void
    {
        if (!$this->authToken(self::STUDENT)) {
            $this->call(401, "unauthorized", "Usuário não está autenticado (sem token ou token inválido).", "error")->back();
            return;
        }

        if (!$this->validate($data) || $this->userAuthSchoolId === null) {
            $this->call(400, "bad_request", "O campo availability_id é obrigatório e deve ser um número inteiro", "error")->back();
            return;
        }

        $availabilityId = (int)$data["availability_id"];
        $appointment = new Appointment();
        if (!$appointment->availabilityBelongsToSchool($availabilityId, (int)$this->userAuthSchoolId)) {
            if ($appointment->getErrorMessage() !== null) {
                $this->call(500, "internal_server_error", "Não foi possível consultar a disponibilidade.", "error")->back();
                return;
            }
            $this->call(404, "not_found", "Disponibilidade não encontrada para a escola do aluno", "error")->back();
            return;
        }

        $connection = Connect::getInstance();
        $connection->beginTransaction();

        if ($appointment->hasActiveBooking($availabilityId, $this->userAuthId)) {
            $connection->rollBack();
            $this->call(409, "conflict", "Você já possui um agendamento ativo neste horário.", "error")->back();
            return;
        }

        if (!$appointment->hasVacancy($availabilityId)) {
            $connection->rollBack();
            $this->call(409, "conflict", "Este horário não possui mais vagas disponíveis.", "error")->back();
            return;
        }

        $appointment = new Appointment(
            null,
            $availabilityId,
            $this->userAuthId,
            isset($data["notes"]) ? trim($data["notes"]) : null,
            1
        );

        if (!$appointment->insert()) {
            $connection->rollBack();
            $this->call(500, "internal_server_error", "Não foi possível registrar o agendamento.", "error")->back();
            return;
        }
        $connection->commit();

        $appointment->selectById((int)$appointment->getId());
        $this->notifyTeacher(
            $availabilityId,
            "Novo pedido de atendimento",
            "Um aluno solicitou um atendimento em um dos seus horários. Confira em Atendimentos."
        );

        $this->call(201, "success", "Agendamento inserido com sucesso", "created")->back($this->responseData($appointment));
    }

    public function update(array $data): void
    {
        if (!$this->authToken(self::STUDENT)) {
            $this->call(401, "unauthorized", "Usuário não está autenticado (sem token ou token inválido).", "error")->back();
            return;
        }

        if (!$this->validateId($data, "appointment_id") || !$this->validate($data) || $this->userAuthSchoolId === null) {
            $this->call(400, "bad_request", "ID inválido ou campos obrigatórios ausentes", "error")->back();
            return;
        }

        $existing = new Appointment();
        if (!$existing->selectById((int)$data["appointment_id"])) {
            $this->call(404, "not_found", "Agendamento não encontrado", "error")->back();
            return;
        }

        if ($existing->getStudentId() !== $this->userAuthId) {
            $this->call(403, "forbidden", "Usuário sem permissão para atualizar este agendamento.", "error")->back();
            return;
        }

        if ($existing->getActive() !== 1 || ($existing->getStatus() ?? "pending") !== "pending") {
            $this->call(400, "bad_request", "Somente agendamentos pendentes podem ser alterados.", "error")->back();
            return;
        }

        $availabilityId = (int)$data["availability_id"];
        if (!$existing->availabilityBelongsToSchool($availabilityId, (int)$this->userAuthSchoolId)) {
            if ($existing->getErrorMessage() !== null) {
                $this->call(500, "internal_server_error", "Não foi possível consultar a disponibilidade.", "error")->back();
                return;
            }
            $this->call(404, "not_found", "Disponibilidade não encontrada para a escola do aluno", "error")->back();
            return;
        }

        $connection = Connect::getInstance();
        $connection->beginTransaction();
        $appointmentId = (int)$data["appointment_id"];
        if ($availabilityId !== $existing->getAvailabilityId()) {
            if ($existing->hasActiveBooking($availabilityId, $this->userAuthId, $appointmentId)) {
                $connection->rollBack();
                $this->call(409, "conflict", "Você já possui um agendamento ativo neste horário.", "error")->back();
                return;
            }
            if (!$existing->hasVacancy($availabilityId, $appointmentId)) {
                $connection->rollBack();
                $this->call(409, "conflict", "Este horário não possui mais vagas disponíveis.", "error")->back();
                return;
            }
        }

        $appointment = new Appointment(
            null,
            $availabilityId,
            null,
            isset($data["notes"]) ? trim($data["notes"]) : null,
            null
        );

        if (!$appointment->updateById($appointmentId)) {
            $connection->rollBack();
            $this->call(500, "internal_server_error", "Não foi possível atualizar o agendamento.", "error")->back();
            return;
        }
        $connection->commit();

        $appointment->selectById($appointmentId);
        $this->call(200, "success", "Agendamento atualizado com sucesso", "success")->back($this->responseData($appointment));
    }

    public function delete(array $data): void
    {
        if (!$this->authToken(self::STUDENT)) {
            $this->call(401, "unauthorized", "Usuário não está autenticado (sem token ou token inválido).", "error")->back();
            return;
        }

        if (!$this->validateId($data, "appointment_id")) {
            $this->call(400, "bad_request", "ID do agendamento é obrigatório e deve ser um número inteiro", "error")->back();
            return;
        }

        $appointment = new Appointment();
        if (!$appointment->selectById((int)$data["appointment_id"])) {
            $this->call(404, "not_found", "Agendamento não encontrado", "error")->back();
            return;
        }

        if ($appointment->getStudentId() !== $this->userAuthId) {
            $this->call(403, "forbidden", "Usuário sem permissão para excluir este agendamento.", "error")->back();
            return;
        }

        if (!in_array($appointment->getStatus() ?? "pending", ["pending", "approved"], true)) {
            $this->call(400, "bad_request", "Somente agendamentos pendentes ou aprovados podem ser cancelados.", "error")->back();
            return;
        }

        if (!$appointment->updateStatusById((int)$data["appointment_id"], "cancelled")
            || !$appointment->softDeleteById((int)$data["appointment_id"])) {
            $this->call(404, "not_found", "Agendamento não encontrado ou já cancelado", "error")->back();
            return;
        }

        $this->notifyTeacher(
            (int)$appointment->getAvailabilityId(),
            "Atendimento cancelado",
            "Um aluno cancelou um atendimento marcado em um dos seus horários."
        );

        $this->call(200, "success", "Agendamento cancelado com sucesso", "success")->back();
    }

    public function updateStatus(array $data): void
    {
        if (!$this->authToken([self::GLOBAL_ADMIN, self::SCHOOL_ADMIN, self::TEACHER])) {
            $this->call(401, "unauthorized", "Usuário não está autenticado (sem token ou token inválido).", "error")->back();
            return;
        }

        if (!$this->validateId($data, "appointment_id")
            || !isset($data["status"])
            || !in_array($data["status"], ["approved", "rejected", "completed"], true)) {
            $this->call(400, "bad_request", "Informe um status válido para o agendamento.", "error")->back();
            return;
        }

        $appointment = new Appointment();
        if (!$appointment->selectById((int)$data["appointment_id"])) {
            $this->call(404, "not_found", "Agendamento não encontrado.", "error")->back();
            return;
        }
        if (!$this->canAccess((int)$data["appointment_id"], $appointment)) {
            $this->call(403, "forbidden", "Usuário sem permissão para atualizar este agendamento.", "error")->back();
            return;
        }

        $currentStatus = $appointment->getStatus() ?? "pending";
        $validTransition = ($currentStatus === "pending" && in_array($data["status"], ["approved", "rejected"], true))
            || ($currentStatus === "approved" && $data["status"] === "completed");
        if (!$validTransition) {
            $this->call(400, "bad_request", "O status atual não permite essa transição.", "error")->back();
            return;
        }

        if (!$appointment->updateStatusById((int)$data["appointment_id"], $data["status"])) {
            $this->call(500, "internal_server_error", "Não foi possível atualizar o status do agendamento.", "error")->back();
            return;
        }

        $labels = ["approved" => "aprovado", "rejected" => "recusado", "completed" => "concluído"];
        Notification::notify(
            (int)$appointment->getStudentId(),
            "Agendamento " . $labels[$data["status"]],
            "O status do seu agendamento foi atualizado para: " . $labels[$data["status"]] . "."
        );

        $appointment->selectById((int)$data["appointment_id"]);
        $this->call(200, "success", "Status do agendamento atualizado.", "success")->back($this->responseData($appointment));
    }

    public function uploadImages(array $data): void
    {
        if (!$this->authToken(self::STUDENT)) {
            $this->call(401, "unauthorized", "Usuário não está autenticado (sem token ou token inválido).", "error")->back();
            return;
        }
        if (!$this->validateId($data, "appointment_id")) {
            $this->call(400, "bad_request", "ID do agendamento inválido.", "error")->back();
            return;
        }

        $appointmentId = (int)$data["appointment_id"];
        $appointment = new Appointment();
        if (!$appointment->selectById($appointmentId)) {
            $this->call(404, "not_found", "Agendamento não encontrado.", "error")->back();
            return;
        }
        if ($appointment->getStudentId() !== $this->userAuthId || $appointment->getActive() !== 1) {
            $this->call(403, "forbidden", "Somente o autor do agendamento ativo pode enviar imagens.", "error")->back();
            return;
        }

        $files = $this->normalizeImageFiles($_FILES["images"] ?? ($_FILES["image"] ?? null));
        if ($files === false || count($files) === 0 || count($files) > self::MAX_IMAGES_PER_REQUEST) {
            $this->call(400, "bad_request", "Selecione de 1 a 20 imagens por envio.", "error")->back();
            return;
        }

        $uploadDirectory = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . "uploads" . DIRECTORY_SEPARATOR . "appointments";
        $uploader = new Upload($uploadDirectory);
        $position = (new AppointmentImage())->nextPosition($appointmentId);
        $created = [];
        foreach ($files as $file) {
            try {
                $filename = $uploader->image($file);
            } catch (\RuntimeException $exception) {
                $this->rollbackImages($created, $uploadDirectory);
                $this->call(400, "bad_request", $exception->getMessage(), "error")->back();
                return;
            }

            $image = new AppointmentImage(null, $appointmentId, "uploads/appointments/" . $filename, null, $position++);
            if (!$image->insert()) {
                // A gravação do arquivo funcionou, mas o registro falhou: remove o arquivo recém-gravado.
                $this->removeFile($uploadDirectory, $filename);
                $this->rollbackImages($created, $uploadDirectory);
                error_log("Falha ao associar imagem ao agendamento: " . ($image->getErrorMessage() ?? "erro desconhecido"));
                $this->call(500, "internal_server_error", "Não foi possível associar as imagens ao agendamento.", "error")->back();
                return;
            }
            $created[] = ["id" => (int)$image->getId(), "filename" => $filename];
        }

        $this->call(201, "success", "Imagens associadas ao agendamento.", "created")->back(
            $this->imageResponse($appointmentId)
        );
    }

    public function listImages(array $data): void
    {
        if (!$this->authToken([self::GLOBAL_ADMIN, self::SCHOOL_ADMIN, self::TEACHER, self::STUDENT])) {
            $this->call(401, "unauthorized", "Usuário não está autenticado (sem token ou token inválido).", "error")->back();
            return;
        }
        if (!$this->validateId($data, "appointment_id")) {
            $this->call(400, "bad_request", "ID do agendamento inválido.", "error")->back();
            return;
        }

        $appointment = new Appointment();
        if (!$appointment->selectById((int)$data["appointment_id"])) {
            $this->call(404, "not_found", "Agendamento não encontrado.", "error")->back();
            return;
        }
        if (!$this->canAccess((int)$data["appointment_id"], $appointment)) {
            $this->call(403, "forbidden", "Usuário sem permissão para consultar as imagens.", "error")->back();
            return;
        }

        $this->call(200, "success", "Imagens do agendamento", "success")->back(
            $this->imageResponse((int)$data["appointment_id"])
        );
    }

    public function deleteImage(array $data): void
    {
        if (!$this->authToken([self::GLOBAL_ADMIN, self::STUDENT])) {
            $this->call(401, "unauthorized", "Usuário não está autenticado (sem token ou token inválido).", "error")->back();
            return;
        }
        if (!$this->validateId($data, "appointment_id") || !$this->validateId($data, "image_id")) {
            $this->call(400, "bad_request", "ID do agendamento ou da imagem inválido.", "error")->back();
            return;
        }

        $image = new AppointmentImage();
        if (!$image->selectById((int)$data["image_id"]) || $image->getAppointmentId() !== (int)$data["appointment_id"]) {
            $this->call(404, "not_found", "Imagem não encontrada neste agendamento.", "error")->back();
            return;
        }

        $appointment = new Appointment();
        if (!$appointment->selectById((int)$data["appointment_id"])) {
            $this->call(404, "not_found", "Agendamento não encontrado.", "error")->back();
            return;
        }
        if ($this->userAuthTypeId !== self::GLOBAL_ADMIN && $appointment->getStudentId() !== $this->userAuthId) {
            $this->call(403, "forbidden", "Somente o aluno autor do agendamento pode excluir as imagens.", "error")->back();
            return;
        }

        $imagePath = (string)$image->getImagePath();
        if (!$image->deleteById((int)$data["image_id"])) {
            $this->call(500, "internal_server_error", "Não foi possível excluir a imagem.", "error")->back();
            return;
        }

        // Remove o arquivo somente depois que o registro saiu do banco.
        $uploadDirectory = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . "uploads" . DIRECTORY_SEPARATOR . "appointments";
        $this->removeFile($uploadDirectory, basename($imagePath));

        $this->call(200, "success", "Imagem excluída.", "success")->back();
    }

    private function validate(array $data): bool
    {
        return $this->validateId($data, "availability_id");
    }

    private function validateId(array $data, string $field): bool
    {
        return isset($data[$field])
            && filter_var($data[$field], FILTER_VALIDATE_INT) !== false
            && (int)$data[$field] > 0;
    }

    private function canAccess(int $appointmentId, Appointment $appointment): bool
    {
        if ($this->userAuthTypeId === self::GLOBAL_ADMIN) {
            return true;
        }

        if ($this->userAuthTypeId === self::STUDENT) {
            return $appointment->getStudentId() === $this->userAuthId;
        }

        $filters = array_merge(["id = {$appointmentId}"], $this->permissionFilters());
        return !empty($appointment->selectAll($filters));
    }

    private function permissionFilters(): array
    {
        $filters = ["active = 1"];

        if ($this->userAuthTypeId === self::GLOBAL_ADMIN) {
            return $filters;
        }

        if ($this->userAuthTypeId === self::SCHOOL_ADMIN) {
            $schoolId = (int)$this->userAuthSchoolId;
            return array_merge($filters, [
                "availability_id IN (SELECT availabilities.id FROM availabilities INNER JOIN subjects ON subjects.id = availabilities.subject_id WHERE subjects.school_id = {$schoolId})"
            ]);
        }

        if ($this->userAuthTypeId === self::TEACHER) {
            return array_merge($filters, [
                "availability_id IN (SELECT id FROM availabilities WHERE teacher_id = " . (int)$this->userAuthId . ")"
            ]);
        }

        return array_merge($filters, ["student_id = " . (int)$this->userAuthId]);
    }

    private function notifyTeacher(int $availabilityId, string $title, string $message): void
    {
        $availability = new Availability();
        if ($availability->selectById($availabilityId) && $availability->getTeacherId() !== null) {
            Notification::notify((int)$availability->getTeacherId(), $title, $message);
        }
    }

    private function responseData(Appointment $appointment): array
    {
        $details = $appointment->detailsById((int)$appointment->getId()) ?? [];
        return array_merge($details, [
            "id" => $appointment->getId(),
            "availability_id" => $appointment->getAvailabilityId(),
            "student_id" => $appointment->getStudentId(),
            "notes" => $appointment->getNotes(),
            "active" => $appointment->getActive(),
            "status" => $appointment->getStatus() ?? ($details["status"] ?? "pending"),
            "created_at" => $appointment->getCreatedAt() ?? ($details["created_at"] ?? null),
            "images" => $this->imageResponse((int)$appointment->getId())
        ]);
    }

    private function formatRows(array $rows): array
    {
        $formatted = [];
        foreach ($rows as $row) {
            $id = is_object($row) ? ($row->id ?? null) : ($row["id"] ?? null);
            if (!is_numeric($id) || (int)$id < 1) {
                continue;
            }
            $appointment = new Appointment();
            if ($appointment->selectById((int)$id)) {
                $formatted[] = $this->responseData($appointment);
            }
        }
        return $formatted;
    }

    private function imageResponse(int $appointmentId): array
    {
        $rows = (new AppointmentImage())->listByAppointmentId($appointmentId);
        if ($rows === false) {
            return [];
        }

        return array_map(fn(object $image): array => [
            "id" => (int)$image->id,
            "appointment_id" => (int)$image->appointment_id,
            "image_path" => $image->image_path,
            "alt_text" => $image->alt_text,
            "position" => (int)$image->position,
            "created_at" => $image->created_at,
            "url" => url($image->image_path)
        ], $rows);
    }

    private function normalizeImageFiles(mixed $files): array|false
    {
        if (!is_array($files) || !isset($files["name"], $files["tmp_name"], $files["error"], $files["size"])) {
            return false;
        }
        if (!is_array($files["name"])) {
            return [$files];
        }

        $normalized = [];
        foreach (array_keys($files["name"]) as $index) {
            $normalized[] = [
                "name" => $files["name"][$index] ?? "",
                "type" => $files["type"][$index] ?? "",
                "tmp_name" => $files["tmp_name"][$index] ?? "",
                "error" => $files["error"][$index] ?? UPLOAD_ERR_NO_FILE,
                "size" => $files["size"][$index] ?? 0
            ];
        }
        return $normalized;
    }

    private function rollbackImages(array $created, string $uploadDirectory): void
    {
        foreach ($created as $image) {
            (new AppointmentImage())->deleteById((int)$image["id"]);
            $this->removeFile($uploadDirectory, $image["filename"]);
        }
    }

    private function removeFile(string $uploadDirectory, string $filename): void
    {
        if (!preg_match('/\A[a-f0-9]{32}\.(?:jpg|png|webp)\z/', $filename)) {
            return;
        }

        $path = $uploadDirectory . DIRECTORY_SEPARATOR . $filename;
        if (is_file($path) && !unlink($path)) {
            error_log("Não foi possível remover a imagem do agendamento.");
        }
    }
}
