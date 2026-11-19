<?php

const CONF_URL_BASE = "http://localhost/ProjectPW-main"; // URL base do site, geralmente localhost em desenvolvimento
const CONF_URL_TEST = "http://localhost/ProjectPW-main"; // URL base do site, geralmente localhost em desenvolvimento


const CONF_DB_HOST = "localhost"; // XAMPP: localhost | Docker: nome do serviço (ex.: "mysql")
const CONF_DB_NAME = "db-acme-tarde";
const CONF_DB_USER= "root";
const CONF_DB_PORT = 3306;
const CONF_DB_PASS= "";

// Chave secreta para criação do token JWT, deve ser uma string complexa e única para cada aplicação
// para gerar a sua acesse: https://jwtsecrets.com/
const JWT_SECRET_KEY = "7cdc0a2fec73ea782d3c06ad708eb7e83e869d682aa9e70e80ded7142605acedca6f142f7b861add0077640ebd66c576b0d7b3013c03976a217317fee7c2dfed";
