import Users from "../classes/Users.js";
import Addresses from "../classes/Addresses.js";
import Address from "../classes/Address.js";

const TYPE_NAMES = { 1: "Administrador global", 2: "Administrador escolar", 3: "Professor", 4: "Aluno" };

// Perfil do usuário autenticado, baseado na classe Profile do projeto do professor.
// A mesma classe atende a área app (professor/aluno) e a área administrativa.
export default class Profile {
    constructor(storage, { admin = false } = {}) {
        this.storage = storage;
        this.admin = admin;
        this.users = new Users();
        this.addresses = new Addresses();
        this.address = null;
        this.photoObjectUrl = null;
    }

    init() {
        // Conecta os formulários e carrega os dados atuais do usuário.
        const token = this.storage.getToken();
        [this.users, this.addresses].forEach((client) => client.setAuthToken(token));

        document.querySelector("[data-profile-form]")?.addEventListener("submit", (event) => {
            this.updateProfile(event);
        });
        document.querySelector("[data-profile-photo-form]")?.addEventListener("submit", (event) => {
            this.updatePhoto(event);
        });
        document.querySelector("[data-profile-photo-input]")?.addEventListener("change", (event) => {
            this.previewPhoto(event.currentTarget.files[0]);
        });
        document.querySelector("[data-address-form]")?.addEventListener("submit", (event) => {
            this.saveAddress(event);
        });
        document.querySelector("[data-address-remove]")?.addEventListener("click", () => {
            this.removeAddress();
        });

        void this.loadProfile();
        void this.loadAddress();
    }

    async loadProfile() {
        // Busca o perfil na API e informa na tela se a operação falhar.
        try {
            const response = await this.users.getMe();
            if (response?.status !== "success" || !response.data) {
                throw new Error(response?.message || "Não foi possível carregar seu perfil.");
            }

            this.renderProfile(response.data.toJSON());
        } catch (error) {
            this.showFeedback("[data-profile-feedback]", error.message || "Não foi possível carregar seu perfil.", "error");
        }
    }

    renderProfile(user) {
        // Preenche o formulário e sincroniza a sessão e o cabeçalho.
        const form = document.querySelector("[data-profile-form]");
        form.elements.namedItem("name").value = user.name || "";
        form.elements.namedItem("email").value = user.email || "";

        const savedUser = this.storeUser(user);
        if (savedUser.token) {
            this.users.setAuthToken(savedUser.token);
            this.addresses.setAuthToken(savedUser.token);
        }
        this.refreshHeader(savedUser);
        document.querySelector("[data-profile-name]").textContent = savedUser.name || "Meu perfil";
        document.querySelector("[data-profile-role]").textContent = TYPE_NAMES[savedUser.type_id] || "Usuário";
        document.querySelector("[data-profile-initials]").textContent = this.getInitials(savedUser.name);
        this.showProfilePhoto(savedUser.photo);
    }

    async updateProfile(event) {
        event.preventDefault();

        // Só permite trocar a senha quando a atual e a nova forem informadas.
        const form = event.currentTarget;
        const button = form.querySelector("[type='submit']");
        const currentPassword = form.elements.namedItem("current_password").value;
        const newPassword = form.elements.namedItem("new_password").value;
        const confirmation = form.elements.namedItem("new_password_confirm").value;

        if (Boolean(currentPassword) !== Boolean(newPassword)) {
            this.showFeedback("[data-profile-feedback]", "Para trocar a senha, preencha a senha atual e a nova senha.", "error");
            return;
        }

        if (newPassword && newPassword.length < 8) {
            this.showFeedback("[data-profile-feedback]", "A nova senha deve ter pelo menos 8 caracteres.", "error");
            return;
        }

        if (newPassword !== confirmation) {
            this.showFeedback("[data-profile-feedback]", "A confirmação da nova senha não confere.", "error");
            return;
        }

        const data = {
            name: form.elements.namedItem("name").value.trim(),
            email: form.elements.namedItem("email").value.trim(),
        };
        if (currentPassword && newPassword) {
            data.current_password = currentPassword;
            data.new_password = newPassword;
        }

        this.setBusy(button, true, "Salvando...");
        this.showFeedback("[data-profile-feedback]", "");

        try {
            const response = this.admin ? await this.users.updateAdmin(data) : await this.users.update(data);
            if (response?.status !== "success" || !response.data) {
                throw new Error(response?.message || "Não foi possível atualizar seu perfil.");
            }

            this.renderProfile(response.data.toJSON());
            ["current_password", "new_password", "new_password_confirm"].forEach((field) => {
                form.elements.namedItem(field).value = "";
            });
            this.showFeedback("[data-profile-feedback]", response.message || "Perfil atualizado com sucesso.", "success");
        } catch (error) {
            this.showFeedback("[data-profile-feedback]", error.message || "Não foi possível atualizar seu perfil.", "error");
        } finally {
            this.setBusy(button, false, "Salvar dados");
        }
    }

    async updatePhoto(event) {
        event.preventDefault();

        // Valida o arquivo no navegador antes de enviá-lo à API.
        const form = event.currentTarget;
        const button = form.querySelector("[type='submit']");
        const input = form.elements.namedItem("photo");
        const photo = input.files[0];
        const feedback = "[data-profile-photo-feedback]";

        if (!photo) {
            this.showFeedback(feedback, "Selecione uma imagem para continuar.", "error");
            return;
        }

        if (photo.size > 5 * 1024 * 1024) {
            this.showFeedback(feedback, "A imagem deve ter no máximo 5 MB.", "error");
            return;
        }

        if (photo.type && !["image/jpeg", "image/png", "image/webp"].includes(photo.type)) {
            this.showFeedback(feedback, "Escolha uma imagem JPG, PNG ou WEBP.", "error");
            return;
        }

        this.setBusy(button, true, "Enviando...");
        this.showFeedback(feedback, "");

        try {
            const response = await this.users.uploadPhoto(new FormData(form));
            if (response?.status !== "success" || !response.data?.photo) {
                throw new Error(response?.message || "Não foi possível atualizar sua foto.");
            }

            const user = this.storeUser({ photo: response.data.photo });
            document.querySelector("[data-profile-initials]").textContent = this.getInitials(user.name);
            form.reset();
            this.revokePhotoPreview();
            this.showProfilePhoto(user.photo);
            this.refreshHeader(user);
            this.showFeedback(feedback, response.message || "Foto atualizada com sucesso.", "success");
        } catch (error) {
            this.showFeedback(feedback, error.message || "Não foi possível atualizar sua foto.", "error");
        } finally {
            this.setBusy(button, false, "Atualizar foto");
        }
    }

    async loadAddress() {
        const form = document.querySelector("[data-address-form]");
        if (!form) return;

        try {
            const response = await this.addresses.listAll();
            this.address = response.data[0] || null;
            form.elements.namedItem("street").value = this.address?.street || "";
            form.elements.namedItem("number").value = this.address?.number || "";
            document.querySelector("[data-address-remove]").hidden = !this.address;
            if (!this.address) {
                this.showFeedback("[data-address-feedback]", "Nenhum endereço cadastrado.");
            }
        } catch (error) {
            this.showFeedback("[data-address-feedback]", error.message || "Não foi possível carregar o endereço.", "error");
        }
    }

    async saveAddress(event) {
        event.preventDefault();
        const form = event.currentTarget;
        const button = form.querySelector("[type='submit']");

        let address;
        try {
            address = new Address({
                street: form.elements.namedItem("street").value,
                number: form.elements.namedItem("number").value
            });
            address.toPayload();
        } catch (error) {
            this.showFeedback("[data-address-feedback]", error.message, "error");
            return;
        }

        this.setBusy(button, true, "Salvando...");
        try {
            const response = this.address
                ? await this.addresses.update(this.address.id, address)
                : await this.addresses.insert(address);
            this.address = response.data;
            document.querySelector("[data-address-remove]").hidden = false;
            this.showFeedback("[data-address-feedback]", response.message || "Endereço salvo com sucesso.", "success");
        } catch (error) {
            this.showFeedback("[data-address-feedback]", error.message || "Não foi possível salvar o endereço.", "error");
        } finally {
            this.setBusy(button, false, "Salvar endereço");
        }
    }

    async removeAddress() {
        if (!this.address || !window.confirm("Remover o endereço cadastrado?")) return;

        try {
            const response = await this.addresses.remove(this.address.id);
            this.address = null;
            document.querySelector("[data-address-form]").reset();
            document.querySelector("[data-address-remove]").hidden = true;
            this.showFeedback("[data-address-feedback]", response.message || "Endereço removido.", "success");
        } catch (error) {
            this.showFeedback("[data-address-feedback]", error.message || "Não foi possível remover o endereço.", "error");
        }
    }

    previewPhoto(photo) {
        // Cria uma prévia local; o arquivo só é enviado ao salvar o formulário.
        this.revokePhotoPreview();
        if (!photo) {
            const user = this.storage.getUser() || {};
            this.showProfilePhoto(user.photo);
            return;
        }

        this.photoObjectUrl = URL.createObjectURL(photo);
        this.showProfilePhoto(this.photoObjectUrl);
    }

    showProfilePhoto(photo) {
        // Mostra as iniciais como alternativa se a foto estiver ausente ou falhar.
        const image = document.querySelector("[data-profile-photo]");
        const initials = document.querySelector("[data-profile-initials]");
        const photoUrl = this.getSafePhotoUrl(photo);

        image.hidden = true;
        initials.hidden = false;
        image.onerror = () => {
            image.hidden = true;
            initials.hidden = false;
        };
        image.onload = () => {
            image.hidden = false;
            initials.hidden = true;
        };

        if (photoUrl) {
            image.src = photoUrl;
        } else {
            image.removeAttribute("src");
        }
    }

    refreshHeader(user) {
        // Atualiza nome e iniciais na barra lateral sem recarregar a página.
        document.querySelectorAll(".sidebar-user-name").forEach((element) => {
            element.textContent = user.name || "Minha conta";
        });
        document.querySelectorAll(".sidebar-user .avatar").forEach((avatar) => {
            avatar.textContent = this.getInitials(user.name);
        });
    }

    storeUser(changes) {
        // Mescla os dados novos com a sessão atual para não perder outras informações.
        const user = { ...(this.storage.getUser() || {}), ...changes };
        const token = user.token || this.storage.getToken();
        if (token) {
            this.storage.saveSession(token, user);
        }
        return user;
    }

    getInitials(name = "") {
        return String(name || "").split(" ").filter(Boolean).slice(0, 2).map((part) => part[0]).join("").toUpperCase() || "--";
    }

    getSafePhotoUrl(photo) {
        // Restringe imagens a URLs HTTP(S) ou à prévia local do navegador.
        if (typeof photo !== "string" || photo === "") {
            return null;
        }

        try {
            const url = new URL(photo, window.location.origin);
            return ["http:", "https:", "blob:"].includes(url.protocol) ? url.href : null;
        } catch {
            return null;
        }
    }

    revokePhotoPreview() {
        // Libera a URL temporária criada para a prévia da imagem.
        if (this.photoObjectUrl) {
            URL.revokeObjectURL(this.photoObjectUrl);
            this.photoObjectUrl = null;
        }
    }

    showFeedback(selector, message, type = "") {
        const element = document.querySelector(selector);
        if (!element) {
            return;
        }

        element.textContent = message;
        element.className = `profile-feedback${type ? ` is-${type}` : ""}`;
    }

    setBusy(button, busy, label) {
        button.disabled = busy;
        button.textContent = busy ? "Aguarde..." : label;
    }
}
