export default class FaqCategory {
    #id;
    #name;
    #active;

    constructor(data = {}) {
        this.id = data.id ?? null;
        this.name = data.name ?? "";
        this.active = data.active ?? 1;
    }

    get id() { return this.#id; }
    set id(value) {
        if (value === null || value === "") this.#id = null;
        else if (Number.isInteger(Number(value)) && Number(value) > 0) this.#id = Number(value);
        else throw new RangeError("O ID da categoria deve ser positivo.");
    }

    get name() { return this.#name; }
    set name(value) {
        if (typeof value !== "string" || !value.trim()) throw new TypeError("O nome da categoria é obrigatório.");
        this.#name = value.trim();
    }

    get active() { return this.#active; }
    set active(value) {
        const number = Number(value);
        if (![0, 1].includes(number)) throw new RangeError("O estado deve ser 0 ou 1.");
        this.#active = number;
    }

    static fromAPI(data = {}) { return new FaqCategory(data); }

    toPayload() {
        return { name: this.name };
    }

    toJSON() {
        return { id: this.id, name: this.name, active: this.active };
    }
}
