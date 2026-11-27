export default class Contact {
    static SUBJECTS = ["demo", "planos", "suporte", "parceria", "outro"];

    #id;
    #name;
    #email;
    #subject;
    #message;
    #createdAt;

    constructor(data = {}) {
        this.id = data.id ?? null;
        this.name = data.name ?? "";
        this.email = data.email ?? "";
        this.subject = data.subject ?? "";
        this.message = data.message ?? "";
        this.createdAt = data.created_at ?? data.createdAt ?? null;
    }

    get id() { return this.#id; }
    set id(value) { this.#id = value === null || value === "" ? null : Number(value); }

    get name() { return this.#name; }
    set name(value) { this.#name = String(value ?? "").trim(); }

    get email() { return this.#email; }
    set email(value) { this.#email = String(value ?? "").trim(); }

    get subject() { return this.#subject; }
    set subject(value) { this.#subject = String(value ?? ""); }

    get message() { return this.#message; }
    set message(value) { this.#message = String(value ?? "").trim(); }

    get createdAt() { return this.#createdAt; }
    set createdAt(value) { this.#createdAt = value ?? null; }

    static fromAPI(data = {}) { return new Contact(data); }

    validate() {
        if (!this.name) throw new TypeError("Informe seu nome.");
        if (!/^\S+@\S+\.\S+$/.test(this.email)) throw new TypeError("Informe um e-mail válido.");
        if (!Contact.SUBJECTS.includes(this.subject)) throw new TypeError("Selecione o assunto.");
        if (!this.message) throw new TypeError("Escreva a mensagem.");
    }

    toPayload() {
        this.validate();
        return { name: this.name, email: this.email, subject: this.subject, message: this.message };
    }
}
