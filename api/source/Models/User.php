<?php

namespace Source\Models;

use PDO;
use Source\Core\Connect;
use Source\Core\JWTToken;
use Source\Core\Model;

class User extends Model
{
    private ?int $id = null;
    private ?int $schoolId = null;
    private ?int $typeId = null;
    private ?string $name = null;
    private ?string $email = null;
    private ?string $password = null;
    private ?string $phone = null;
    private ?string $photo = null;
    private ?string $registrationNumber = null;
    private ?string $specialization = null;
    private ?string $officeRoom = null;
    private ?int $active = null;

    private ?string $token = null;

    public function __construct(
        ?int $id = null,
        ?int $schoolId = null,
        ?int $typeId = null,
        ?string $name = null,
        ?string $email = null,
        ?string $password = null,
        ?string $phone = null,
        ?string $photo = null,
        ?string $registrationNumber = null,
        ?string $specialization = null,
        ?string $officeRoom = null,
        ?int $active = null
    ) {
        $this->id = $id;
        $this->schoolId = $schoolId;
        $this->typeId = $typeId;
        $this->name = $name;
        $this->email = $email;
        $this->password = $password;
        $this->phone = $phone;
        $this->photo = $photo;
        $this->registrationNumber = $registrationNumber;
        $this->specialization = $specialization;
        $this->officeRoom = $officeRoom;
        $this->active = $active;

        $this->table = 'users';
        $this->primaryKey = 'id';
        $this->fillable = [
            'schoolId',
            'typeId',
            'name',
            'email',
            'password',
            'phone',
            'photo',
            'registrationNumber',
            'specialization',
            'officeRoom',
            'active'
        ];
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getSchoolId(): ?int
    {
        return $this->schoolId;
    }

    public function setSchoolId(?int $schoolId): void
    {
        $this->schoolId = $schoolId;
    }

    public function getTypeId(): ?int
    {
        return $this->typeId;
    }

    public function setTypeId(?int $typeId): void
    {
        $this->typeId = $typeId;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): void
    {
        $this->email = $email;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(?string $password): void
    {
        $this->password = $password;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): void
    {
        $this->phone = $phone;
    }

    public function getPhoto(): ?string
    {
        return $this->photo;
    }

    public function setPhoto(?string $photo): void
    {
        $this->photo = $photo;
    }

    public function getRegistrationNumber(): ?string
    {
        return $this->registrationNumber;
    }

    public function setRegistrationNumber(?string $registrationNumber): void
    {
        $this->registrationNumber = $registrationNumber;
    }

    public function getSpecialization(): ?string
    {
        return $this->specialization;
    }

    public function setSpecialization(?string $specialization): void
    {
        $this->specialization = $specialization;
    }

    public function getOfficeRoom(): ?string
    {
        return $this->officeRoom;
    }

    public function setOfficeRoom(?string $officeRoom): void
    {
        $this->officeRoom = $officeRoom;
    }

    public function getActive(): ?int
    {
        return $this->active;
    }

    public function setActive(?int $active): void
    {
        $this->active = $active;
    }

    public function getToken(): ?string
    {
        return $this->token;
    }


    public function insert(): bool
    {
        if ($this->emailExists($this->email)) {
            $this->errorMessage = "Email já cadastrado";
            return false;
        }

        if ($this->password !== null) {
            $this->password = password_hash($this->password, PASSWORD_DEFAULT);
        }

        if ($this->active === null) {
            $this->active = 1;
        }

        if (!parent::insert()) {
            return false;
        }

        return true;
    }

    public function login(string $email, string $password, int|array $typeId = [3, 4]): bool
    {
        $types = $this->typePlaceholders($typeId);
        $query = "SELECT * FROM {$this->table} WHERE email = :email AND type_id IN ({$types['sql']}) AND active = 1 LIMIT 1";
        $stmt = Connect::getInstance()->prepare($query);
        $stmt->bindParam(":email", $email);
        foreach ($types["values"] as $placeholder => $value) {
            $stmt->bindValue($placeholder, $value, PDO::PARAM_INT);
        }
        $stmt->execute();
        if ($stmt->rowCount() == 0) {
            $this->errorMessage = "Email não cadastrado";
            return false;
        }
        $user = $stmt->fetch();
        if (!password_verify($password, $user->password)) {
            $this->errorMessage = "Senha incorreta";
            return false;
        }

        $this->hydrate($user);

        $jwt = new JWTToken();
        // definir quais informações irão par o payload do token
        $this->token = $jwt->encode([
            "id" => (int)$user->id,
            "school_id" => $user->school_id !== null ? (int)$user->school_id : null,
            "type_id" => (int)$user->type_id,
            "name" => $user->name,
            "email" => $user->email
        ]);

        return true;
    }

    public function permissionVerify(string $email, int|array $typeId): bool
    {
        $types = $this->typePlaceholders($typeId);
        $query = "SELECT * FROM {$this->table} WHERE email = :email AND type_id IN ({$types['sql']}) AND active = 1 LIMIT 1";
        $stmt = Connect::getInstance()->prepare($query);
        $stmt->bindParam(":email", $email);
        foreach ($types["values"] as $placeholder => $value) {
            $stmt->bindValue($placeholder, $value, PDO::PARAM_INT);
        }
        $stmt->execute();
        if ($stmt->rowCount() == 0) {
            return false;
        }

        $this->hydrate($stmt->fetch());
        return true;
    }

    public function updateProfile(
        int $id,
        string $name,
        string $email,
        ?string $currentPassword = null,
        ?string $newPassword = null
    ): bool {
        try {
            $query = "SELECT * FROM {$this->table} WHERE id = :id AND active = 1 LIMIT 1";
            $stmt = Connect::getInstance()->prepare($query);
            $stmt->bindValue(":id", $id, PDO::PARAM_INT);
            $stmt->execute();
            $currentUser = $stmt->fetch();

            if (!$currentUser) {
                $this->errorMessage = "Perfil não encontrado.";
                return false;
            }

            if ($this->emailExists($email, $id)) {
                $this->errorMessage = "Este e-mail já está cadastrado.";
                return false;
            }

            if ($currentPassword !== null || $newPassword !== null) {
                if ($currentPassword === null || $newPassword === null || strlen($newPassword) < 8) {
                    $this->errorMessage = "Informe a senha atual e uma nova senha com pelo menos 8 caracteres.";
                    return false;
                }

                if (!password_verify($currentPassword, $currentUser->password)) {
                    $this->errorMessage = "A senha atual está incorreta.";
                    return false;
                }
            }

            $params = [
                ":name" => $name,
                ":email" => $email,
                ":id" => $id,
            ];
            $setParts = ["name = :name", "email = :email"];
            if ($newPassword !== null) {
                $setParts[] = "password = :password";
                $params[":password"] = password_hash($newPassword, PASSWORD_DEFAULT);
            }

            $query = "UPDATE {$this->table} SET " . implode(", ", $setParts) . " WHERE id = :id AND active = 1";
            $stmt = Connect::getInstance()->prepare($query);
            $stmt->execute($params);

            $this->hydrate($currentUser);
            $this->name = $name;
            $this->email = $email;
            $this->password = null;
            $this->token = (new JWTToken())->encode([
                "id" => $this->id,
                "school_id" => $this->schoolId,
                "type_id" => $this->typeId,
                "name" => $name,
                "email" => $email,
            ]);

            return true;
        } catch (\PDOException $exception) {
            $this->errorMessage = (string)$exception->getCode() === "23000"
                ? "Este e-mail já está cadastrado."
                : "Não foi possível atualizar o perfil.";
            return false;
        }
    }

    public function updatePhoto(int $id, string $photo): bool
    {
        try {
            $query = "UPDATE {$this->table} SET photo = :photo WHERE id = :id AND active = 1";
            $stmt = Connect::getInstance()->prepare($query);
            $stmt->bindValue(":photo", $photo);
            $stmt->bindValue(":id", $id, PDO::PARAM_INT);
            $stmt->execute();

            if ($stmt->rowCount() !== 1) {
                $this->errorMessage = "Perfil não encontrado ou foto não alterada.";
                return false;
            }

            $this->id = $id;
            $this->photo = $photo;
            return true;
        } catch (\PDOException $exception) {
            $this->errorMessage = "Não foi possível atualizar a foto do perfil.";
            return false;
        }
    }

    public function emailExists(?string $email, ?int $ignoreId = null): bool
    {
        if ($email === null) {
            return false;
        }

        $query = "SELECT id FROM {$this->table} WHERE email = :email";
        if ($ignoreId !== null) {
            $query .= " AND id <> :id";
        }
        $query .= " LIMIT 1";

        $stmt = Connect::getInstance()->prepare($query);
        $stmt->bindParam(":email", $email);
        if ($ignoreId !== null) {
            $stmt->bindValue(":id", $ignoreId, PDO::PARAM_INT);
        }
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    public function countActiveBySchoolAndType(int $schoolId, int $typeId): ?int
    {
        try {
            $stmt = Connect::getInstance()->prepare(
                "SELECT COUNT(*) FROM {$this->table} WHERE school_id = :school_id AND type_id = :type_id AND active = 1"
            );
            $stmt->bindValue(":school_id", $schoolId, PDO::PARAM_INT);
            $stmt->bindValue(":type_id", $typeId, PDO::PARAM_INT);
            $stmt->execute();
            return (int)$stmt->fetchColumn();
        } catch (\PDOException $exception) {
            $this->errorMessage = "Não foi possível consultar os usuários da escola.";
            return null;
        }
    }

    public function selectSafeAll(): array|false
    {
        $query = "SELECT id, school_id, type_id, name, email, phone, photo, registration_number, specialization, office_room, active FROM {$this->table} ORDER BY id ASC";

        try {
            $stmt = Connect::getInstance()->query($query);
            $users = [];
            foreach ($stmt->fetchAll() as $user) {
                $users[] = $this->makeSafeUser($user);
            }
            return $users;
        } catch (\PDOException $exception) {
            $this->errorMessage = "Não foi possível consultar os usuários.";
            return false;
        }
    }

    public function selectSafeBySchool(int $schoolId): array|false
    {
        $query = "SELECT id, school_id, type_id, name, email, phone, photo, registration_number, specialization, office_room, active FROM {$this->table} WHERE school_id = :school_id AND type_id IN (2, 3, 4) ORDER BY type_id ASC, id ASC";
        try {
            $stmt = Connect::getInstance()->prepare($query);
            $stmt->bindValue(':school_id', $schoolId, PDO::PARAM_INT);
            $stmt->execute();
            $users = [];
            foreach ($stmt->fetchAll() as $user) {
                $users[] = $this->makeSafeUser($user);
            }
            return $users;
        } catch (\PDOException $exception) {
            $this->errorMessage = 'Não foi possível consultar os usuários da escola.';
            return false;
        }
    }

    public function selectSafeById(int $id): bool
    {
        $query = "SELECT id, school_id, type_id, name, email, phone, photo, registration_number, specialization, office_room, active FROM {$this->table} WHERE {$this->primaryKey} = :id LIMIT 1";

        try {
            $stmt = Connect::getInstance()->prepare($query);
            $stmt->bindValue(":id", $id, PDO::PARAM_INT);
            $stmt->execute();
            $user = $stmt->fetch();
            if (!$user) {
                $this->errorMessage = "Usuário não encontrado.";
                return false;
            }

            $this->hydrate($user);
            return true;
        } catch (\PDOException $exception) {
            $this->errorMessage = "Não foi possível consultar o usuário.";
            return false;
        }
    }

    public function updateAdminById(int $id, array $data, ?int $schoolId = null): bool
    {
        $columns = [
            "school_id" => "school_id",
            "type_id" => "type_id",
            "name" => "name",
            "email" => "email",
            "password" => "password",
            "phone" => "phone",
            "registration_number" => "registration_number",
            "specialization" => "specialization",
            "office_room" => "office_room",
            "active" => "active"
        ];

        $fields = array_intersect_key($data, $columns);
        if (empty($fields)) {
            $this->errorMessage = "Nenhum campo válido para atualização.";
            return false;
        }

        if (isset($fields["email"]) && $this->emailExists((string)$fields["email"], $id)) {
            $this->errorMessage = "Email já cadastrado";
            return false;
        }

        if (!empty($fields["password"])) {
            $fields["password"] = password_hash($fields["password"], PASSWORD_DEFAULT);
        } else {
            unset($fields["password"]);
        }

        if (array_key_exists("school_id", $fields) && ($fields["school_id"] === "" || $fields["school_id"] === null)) {
            $fields["school_id"] = null;
        }

        if (empty($fields)) {
            $this->errorMessage = "Nenhum campo válido para atualização.";
            return false;
        }

        $setParts = [];
        foreach (array_keys($fields) as $field) {
            $setParts[] = "{$columns[$field]} = :{$field}";
        }

        try {
            $query = "UPDATE {$this->table} SET " . implode(", ", $setParts) . " WHERE {$this->primaryKey} = :id";
            if ($schoolId !== null) {
                $query .= " AND school_id = :scope_school_id AND type_id IN (2, 3, 4)";
            }
            $stmt = Connect::getInstance()->prepare($query);

            foreach ($fields as $field => $value) {
                $stmt->bindValue(":" . $field, $value);
            }
            $stmt->bindValue(":id", $id, PDO::PARAM_INT);
            if ($schoolId !== null) {
                $stmt->bindValue(":scope_school_id", $schoolId, PDO::PARAM_INT);
            }
            return $stmt->execute();
        } catch (\PDOException $exception) {
            error_log("Falha na atualização administrativa do usuário: " . $exception->getMessage());
            $this->errorMessage = "Não foi possível atualizar o usuário.";
            return false;
        }
    }

    public function deactivateById(int $id, ?int $schoolId = null): bool
    {
        try {
            $query = "UPDATE {$this->table} SET active = 0 WHERE {$this->primaryKey} = :id AND active = 1";
            if ($schoolId !== null) {
                $query .= ' AND school_id = :school_id AND type_id IN (2, 3, 4)';
            }
            $stmt = Connect::getInstance()->prepare($query);
            $stmt->bindValue(":id", $id, PDO::PARAM_INT);
            if ($schoolId !== null) {
                $stmt->bindValue(':school_id', $schoolId, PDO::PARAM_INT);
            }
            $stmt->execute();

            if ($stmt->rowCount() < 1) {
                $this->errorMessage = "Usuário não encontrado ou já inativo.";
                return false;
            }
            return true;
        } catch (\PDOException $exception) {
            error_log("Falha na exclusão lógica do usuário: " . $exception->getMessage());
            $this->errorMessage = "Não foi possível excluir o usuário.";
            return false;
        }
    }

    private function makeSafeUser(object $user): self
    {
        $safeUser = new self();
        $safeUser->hydrate($user);
        $safeUser->password = null;
        return $safeUser;
    }

    private function hydrate(object $user): void
    {
        $this->id = isset($user->id) ? (int)$user->id : null;
        $this->schoolId = isset($user->school_id) ? (int)$user->school_id : null;
        $this->typeId = isset($user->type_id) ? (int)$user->type_id : null;
        $this->name = $user->name ?? null;
        $this->email = $user->email ?? null;
        $this->password = $user->password ?? null;
        $this->phone = $user->phone ?? null;
        $this->photo = $user->photo ?? null;
        $this->registrationNumber = $user->registration_number ?? null;
        $this->specialization = $user->specialization ?? null;
        $this->officeRoom = $user->office_room ?? null;
        $this->active = isset($user->active) ? (int)$user->active : null;
    }

    private function typePlaceholders(int|array $typeId): array
    {
        $values = [];
        foreach (array_values((array)$typeId) as $index => $type) {
            $values[":type_{$index}"] = (int)$type;
        }

        return ["sql" => implode(", ", array_keys($values)), "values" => $values];
    }
}
