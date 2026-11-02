import { afterEach, describe, expect, it } from "vitest";
import { mount, type VueWrapper } from "@vue/test-utils";
import AccountMenu from "../AccountMenu.vue";

let wrapper: VueWrapper | undefined;

function mountMenu() {
  wrapper = mount(AccountMenu, { props: { username: "jana" }, attachTo: document.body });
  return wrapper;
}

afterEach(() => wrapper?.unmount());

describe("AccountMenu", () => {
  it("shows the initial and opens a menu naming the user", async () => {
    const menu = mountMenu();
    const toggle = menu.get("button[aria-label='Account: jana']");

    expect(toggle.text()).toBe("J");
    expect(toggle.attributes("aria-expanded")).toBe("false");

    await toggle.trigger("click");
    expect(toggle.attributes("aria-expanded")).toBe("true");
    expect(menu.get("#account-menu").text()).toContain("Logged in as jana");
  });

  it("emits logOut and closes when Log out is chosen", async () => {
    const menu = mountMenu();
    await menu.get("button[aria-label='Account: jana']").trigger("click");

    await menu.get(".menuitem").trigger("click");

    expect(menu.emitted("logOut")).toHaveLength(1);
    expect(menu.find("#account-menu").exists()).toBe(false);
  });

  it("closes on Escape, returning focus to the button, and on a click outside", async () => {
    const menu = mountMenu();
    const toggle = menu.get("button[aria-label='Account: jana']");

    await toggle.trigger("click");
    await menu.get(".menuitem").trigger("keydown", { key: "Escape" });
    expect(menu.find("#account-menu").exists()).toBe(false);
    expect(document.activeElement).toBe(toggle.element);

    await toggle.trigger("click");
    document.body.click();
    await menu.vm.$nextTick();
    expect(menu.find("#account-menu").exists()).toBe(false);
  });
});
