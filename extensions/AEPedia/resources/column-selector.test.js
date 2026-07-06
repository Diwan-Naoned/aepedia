const { test, describe } = require("node:test");
const assert = require("node:assert/strict");

const {
  parseCsvLine,
  findHeaders,
  extractEmails,
} = require("./column-selector.js");

describe("parseCsvLine", () => {
  test("plain fields", () => {
    assert.deepEqual(parseCsvLine("a,b,c"), ["a", "b", "c"]);
  });

  test("quoted field containing a comma", () => {
    assert.deepEqual(parseCsvLine('a,"b,c",d'), ["a", "b,c", "d"]);
  });

  test("escaped double quotes inside a quoted field", () => {
    assert.deepEqual(parseCsvLine('a,"b""c",d'), ["a", 'b"c', "d"]);
  });

  test("trailing empty field is preserved", () => {
    assert.deepEqual(parseCsvLine("a,b,"), ["a", "b", ""]);
  });
});

describe("findHeaders", () => {
  test("returns the header line before the first data row", () => {
    const csv = "name,email\nAlice,alice@example.com";
    assert.deepEqual(findHeaders(csv), ["name", "email"]);
  });

  test("returns the last non-data line when several precede the data", () => {
    const csv = "report title\n\ncolA,colB\nAlice,alice@example.com";
    assert.deepEqual(findHeaders(csv), ["colA", "colB"]);
  });

  test("returns null when the first non-empty line is already data", () => {
    const csv = "alice@example.com,bob@example.com";
    assert.equal(findHeaders(csv), null);
  });
});

describe("extractEmails", () => {
  test("single selected column with a header row", () => {
    const csv = [
      "name,email",
      "Alice,alice@example.com",
      "Bob,bob@example.com",
    ].join("\n");

    assert.deepEqual(extractEmails(csv, [1]), [
      "alice@example.com",
      "bob@example.com",
    ]);
  });

  // Regression: previously the inner loop `break`ed after the first match per
  // row, so only email_1 addresses were collected when both columns were
  // selected.
  test("multiple selected columns collect emails from all of them", () => {
    const csv = [
      "name,email_1,email_2",
      "Alice,alice1@ex.com,alice2@ex.com",
      "Bob,bob1@ex.com,bob2@ex.com",
    ].join("\n");

    assert.deepEqual(extractEmails(csv, [1, 2]), [
      "alice1@ex.com",
      "alice2@ex.com",
      "bob1@ex.com",
      "bob2@ex.com",
    ]);
  });

  test("deduplicates across rows and across selected columns", () => {
    const csv = [
      "email_1,email_2",
      "a@x.com,a@x.com",
      "a@x.com,b@x.com",
    ].join("\n");

    assert.deepEqual(extractEmails(csv, [0, 1]), ["a@x.com", "b@x.com"]);
  });

  test("filter excludes whole rows whose filtered cell matches the value", () => {
    const csv = [
      "status,email",
      "active,alice@ex.com",
      "inactive,bob@ex.com",
    ].join("\n");

    assert.deepEqual(
      extractEmails(csv, [1], [{ colIndex: 0, value: "inactive" }]),
      ["alice@ex.com"],
    );
  });

  test("rows shorter than the largest selected column do not crash", () => {
    const csv = [
      "email_1,email_2",
      "alice@ex.com,bob@ex.com",
      "solo@ex.com",
    ].join("\n");

    assert.deepEqual(extractEmails(csv, [0, 1]), [
      "alice@ex.com",
      "bob@ex.com",
      "solo@ex.com",
    ]);
  });

  test("no header row: first line is treated as data", () => {
    const csv = "alice@example.com\nbob@example.com";
    assert.deepEqual(extractEmails(csv, [0]), [
      "alice@example.com",
      "bob@example.com",
    ]);
  });

  test("invalid values in selected columns are skipped", () => {
    const csv = [
      "name,email",
      "Alice,not-an-email",
      "Bob,bob@example.com",
    ].join("\n");

    assert.deepEqual(extractEmails(csv, [1]), ["bob@example.com"]);
  });
});
