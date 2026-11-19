<?php

namespace Source\Controller;

use Source\Models\User;
use Source\Core\JWTToken;

class Api
{
    protected ?int $userAuthId = null;
    protected ?int $userAuthTypeId = null;
    protected ?int $userAuthSchoolId = null;

    public function authToken (int|array $typeId): bool
    {

        $header = getallheaders();

        $token = $header["token"] ?? $header['Authorization'] ?? $header['authorization'] ?? null;

        if(!$token){
            return false;
        }

        if(str_starts_with($token, 'Bearer ')){
            $token = substr($token, 7);
        }

        $jwt = new JWTToken();

        $jwtToken = $jwt->decode($token);

        if(!$jwtToken){
            return false;
        }

        $tokenTypeId = $jwtToken->data->type_id ?? null;
        if ($tokenTypeId !== null && !in_array((int)$tokenTypeId, (array)$typeId, true)) {
            return false;
        }

        $user = new User();
        if(empty($jwtToken->data->id) || empty($jwtToken->data->email) ||
            !$user->permissionVerify($jwtToken->data->email, $typeId) ||
            $user->getId() !== (int)$jwtToken->data->id){
            return false;
        }

        $this->userAuthId = (int)$jwtToken->data->id;
        $this->userAuthTypeId = $user->getTypeId();
        $this->userAuthSchoolId = $user->getSchoolId();

        return true;

    }

    protected function call (int $code, ?string $status = null, ?string $message = null, ?string $type = null): Api
    {
        http_response_code($code);
        if(!empty($status)){
            $this->response = [
                "code" => $code,
                "type" => $type,
                "status" => $status,
                "message" => (!empty($message) ? $message : null)
            ];
        }
        return $this;
    }

    protected function back(object | array $data = null): Api
    {
        header('Content-Type: application/json');
        if ($data !== null) {
            $this->response["data"] = $data;
        }
        echo json_encode($this->response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $this;
    }

}
