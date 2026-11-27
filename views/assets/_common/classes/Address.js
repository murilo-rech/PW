export default class Address {
    #id;
    #userId;
    #street;
    #number;
    #active;

    constructor(data = {}) {
        this.id = data.id ?? null;
        this.userId = data.user_id ?? data.userId ?? null;
        this.street = data.street ?? "";
        this.number = data.number ?? "";
        this.active = data.active ?? 1;
    }

    get id() { return this.#id; }
    set id(value) { this.#id = value === null || value === "" ? null : Number(value); }

    get userId() { return this.#userId; }
    set userId(value) { this.#userId = value === null || value === "" ? null : Number(value); }

    get street() { return this.#street; }
    set street(value) {
        if (typeof value !== "string") throw new TypeError("A rua deve ser um texto.");
        if (value.trim().length > 150) throw new RangeError("A rua deve ter no máximo 150 caracteres.");
        this.#street = value.trim();
    }

    get number() { return this.#number; }
    set number(value) {
        const text = String(value ?? "").trim();
        if (text.length > 20) throw new RangeError("O número deve ter no máximo 20 caracteres.");
        this.#number = text;
    }

    get active() { return this.#active; }
    set active(value) { this.#active = Number(value) === 0 ? 0 : 1; }

    get isEmpty() { return this.#street === "" && this.#number === ""; }

    static fromAPI(data = {}) { return new Address(data); }

    toPayload() {
        if (!this.street || !this.number) throw new TypeError("Informe a rua e o número do endereço.");
        return { street: this.street, number: this.number };
    }

    toJSON() {
        return { id: this.id, user_id: this.userId, street: this.street, number: this.number, active: this.active };
    }
}
