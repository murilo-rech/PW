export default class Faq {
    #id;
    #faqsCategoryId;
    #question;
    #answer;
    #active;
    #sortOrder;

    constructor(data = {}) {
        this.id = data.id ?? null;
        this.faqsCategoryId = data.faqs_category_id ?? data.faqsCategoryId ?? null;
        this.question = data.question ?? "";
        this.answer = data.answer ?? "";
        this.active = data.active ?? 1;
        this.sortOrder = data.sort_order ?? data.sortOrder ?? null;
    }

    get id() { return this.#id; }
    set id(value) {
        if (value === null || value === "") this.#id = null;
        else if (Number.isInteger(Number(value)) && Number(value) > 0) this.#id = Number(value);
        else throw new RangeError("O ID da pergunta deve ser positivo.");
    }

    get faqsCategoryId() { return this.#faqsCategoryId; }
    set faqsCategoryId(value) {
        if (value === null || value === "") this.#faqsCategoryId = null;
        else if (Number.isInteger(Number(value)) && Number(value) > 0) this.#faqsCategoryId = Number(value);
        else throw new RangeError("Selecione uma categoria válida.");
    }

    get question() { return this.#question; }
    set question(value) {
        if (typeof value !== "string" || !value.trim()) throw new TypeError("A pergunta é obrigatória.");
        this.#question = value.trim();
    }

    get answer() { return this.#answer; }
    set answer(value) {
        if (typeof value !== "string" || !value.trim()) throw new TypeError("A resposta é obrigatória.");
        this.#answer = value.trim();
    }

    get active() { return this.#active; }
    set active(value) {
        const number = Number(value);
        if (![0, 1].includes(number)) throw new RangeError("O estado deve ser 0 ou 1.");
        this.#active = number;
    }

    get sortOrder() { return this.#sortOrder; }
    set sortOrder(value) {
        if (value == null || value === "") this.#sortOrder = null;
        else if (Number.isInteger(Number(value)) && Number(value) >= 0) this.#sortOrder = Number(value);
        else throw new RangeError("A ordem deve ser um número inteiro maior ou igual a zero.");
    }

    get isPublished() { return this.#active === 1; }

    static fromAPI(data = {}) { return new Faq(data); }

    toPayload() {
        if (this.faqsCategoryId === null) throw new TypeError("Selecione a categoria da pergunta.");
        return {
            faqs_category_id: this.faqsCategoryId,
            question: this.question,
            answer: this.answer,
            active: this.active,
            sort_order: this.sortOrder
        };
    }

    toJSON() {
        return { id: this.id, ...this.toPayload() };
    }
}
