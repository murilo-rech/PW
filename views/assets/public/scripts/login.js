import Users from "../../_common/classes/Users.js";
import SessionStorage from "../../_common/scripts/storage.js";
import { createFormController } from "../../_common/classes/FormController.js";

const form = document.querySelector("#form-login");
const message = document.querySelector("#login-mensagem");
const users = new Users();
const formController = createFormController(form);
const params = new URLSearchParams(window.location.search);

if (params.has("email")) form.elements.email.value = params.get("email");
if (["admin", "user"].includes(params.get("access"))) {
    form.elements.access.value = params.get("access");
}

form.addEventListener("submit", async (event) => {
    event.preventDefault();

    const access = form.querySelector('[name="access"]').value;
    formController.setLoading(true, "Entrando...");
    message.textContent = "";
    try {
        const response = access === "admin"
            ? await users.loginAdminFromForm(form)
            : await users.loginFromForm(form);

        const token = response.data?.token;
        if (!token) {
            throw new Error("O servidor não retornou uma sessão válida.");
        }

        // Cada área tem a própria sessão (como no projeto do professor):
        // administradores usam o escopo "admin"; professores e alunos, o escopo padrão.
        const typeId = response.data.typeId;
        const storage = new SessionStorage([1, 2].includes(typeId) ? "admin" : "user");
        storage.saveSession(token, response.data.toJSON());

        if (typeId === 1) {
            window.location.href = "../admin/global-dashboard.html";
        } else if (typeId === 2) {
            window.location.href = "../admin/index.html";
        } else if (typeId === 3) {
            window.location.href = "../app/professor-dashboard.html";
        } else {
            window.location.href = "../app/index.html";
        }
    } catch (error) {
        message.textContent = error.message;
        window.EduUI?.showToast(error, "error");
    } finally {
        formController.setLoading(false);
    }
});
