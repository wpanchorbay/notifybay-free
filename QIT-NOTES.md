# QIT notes for the free plugin

## Where the tests live

In the **Pro** repo, at `../notifybaypro/tests/e2e`. That is the house
convention across these plugin families: the paid plugin owns one suite that
covers both, and the free-only run is produced by deactivating Pro and filtering
out anything tagged `@pro`.

So there are no specs here. A test whose title carries `@pro` is Pro-only;
**an untagged test is a free-plugin test**, and `notifybaypro/tests/run_free_tests.sh`
is what runs them with Pro switched off.

## Two things to know about `qit.json`

**The `test_packages` path points outside this directory**, at the sibling repo.
That resolves — QIT passes profile `test_packages` through to `--test-package`
and `realpath`s a `.`-leading value against the current working directory — but
it means `qit run:e2e` has to be invoked **from this directory**. It is not a
pattern used elsewhere in the house: the sibling families point at a package
inside their own tree. It is here so the free plugin can discover the suite at
all, rather than only ever being tested from Pro's side.

**It does not give you a free-only run.** Pro appears in
`environments.default.plugins` (it has to, so the Pro-tagged tests can run), and
QIT may activate everything it installs. Isolation comes from deactivating Pro at
runtime plus `--grep-invert '@pro'` — both of which live in `run_free_tests.sh`,
not here.

## What is still not gated

`notifybaypro/tests/run_qit_managed_tests.sh` runs the eleven managed test types
(`run:activation`, `run:security`, `run:plugin-check`, and so on) against the
**Pro zip only**. Nothing equivalent runs against this plugin's zip, even though
this is the one that goes to wordpress.org, and `run:plugin-check` /
`run:security` are close to what that review actually checks. `package.sh` here
already produces a suitable zip.
