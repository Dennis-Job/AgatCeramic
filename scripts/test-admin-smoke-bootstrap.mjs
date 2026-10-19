#!/usr/bin/env node

import assert from "node:assert/strict";
import { spawn } from "node:child_process";
import { mkdtemp, rm, writeFile } from "node:fs/promises";
import { tmpdir } from "node:os";
import { dirname, join, resolve } from "node:path";
import { fileURLToPath } from "node:url";

const root = resolve(dirname(fileURLToPath(import.meta.url)), "..");
const project =
  process.env.AGATCERAMIC_SMOKE_PROJECT ??
  `agatceramic-cold-smoke-${process.pid}`;
assert.match(
  project,
  /^agatceramic-(?:coldfix|cold-smoke)-[a-z0-9-]+$/,
  "Use a dedicated cold-smoke project",
);
const compose = [
  "compose",
  "--project-name",
  project,
  "--env-file",
  ".env.example",
  "--profile",
  "admin-smoke",
];

async function docker(args, { capture = false, allowFailure = false } = {}) {
  const result = await new Promise((resolveResult, reject) => {
    const child = spawn("docker", args, {
      cwd: root,
      stdio: ["ignore", capture ? "pipe" : "inherit", "inherit"],
    });
    let output = "";
    child.stdout?.on("data", (chunk) => {
      output += chunk;
    });
    child.on("error", reject);
    child.on("close", (code) => resolveResult({ code, output: output.trim() }));
  });
  if (!allowFailure)
    assert.equal(result.code, 0, `docker ${args.join(" ")} failed`);
  return result;
}

const config = JSON.parse(
  (await docker([...compose, "config", "--format", "json"], { capture: true }))
    .output,
);
for (const [runtime, bootstrap] of [
  ["admin-smoke-backend", "admin-smoke-backend-bootstrap"],
  ["admin-smoke", "admin-smoke-bootstrap"],
]) {
  assert.equal(
    config.services[runtime].depends_on[bootstrap]?.condition,
    "service_completed_successfully",
    `${runtime} must wait for successful bootstrap before its HTTP health budget starts`,
  );
  assert.ok(config.services[bootstrap], `${bootstrap} must exist`);
  const setup = config.services[bootstrap].command;
  assert.equal(
    setup[0],
    "timeout",
    `${bootstrap} must have a process deadline`,
  );
  assert.match(
    setup[3],
    /^[1-9]\d*s$/,
    `${bootstrap} must have a finite deadline`,
  );
  assert.ok(
    Number.parseInt(setup[3], 10) <= 360,
    `${bootstrap} deadline exceeds the cold-install budget`,
  );
  assert.ok(
    setup.includes("--kill-after=10s"),
    `${bootstrap} must bound termination grace`,
  );
  assert.ok(
    !config.services[runtime].command
      .join(" ")
      .includes("install-locked-dependencies"),
    `${runtime} must reserve its health budget for the server`,
  );
}
const bundleMounts = ["admin-smoke-bootstrap", "admin-smoke"].map((service) =>
  config.services[service].volumes.find(
    (mount) => mount.target === "/app/dist",
  ),
);
for (const mount of bundleMounts) {
  assert.equal(
    mount?.type,
    "volume",
    "Admin smoke bundle must not overwrite the host dist",
  );
  assert.equal(
    mount?.source,
    "admin_smoke_dist",
    "Admin smoke must share its isolated bundle volume",
  );
}
console.log("Compose bootstrap dependency gates passed.");
if (process.argv.includes("--config-only")) process.exit(0);

async function resources() {
  const filter = `label=com.docker.compose.project=${project}`;
  const results = [];
  for (const args of [
    ["ps", "-aq", "--filter", filter],
    ["network", "ls", "-q", "--filter", filter],
    ["volume", "ls", "-q", "--filter", filter],
  ]) {
    results.push((await docker(args, { capture: true })).output);
  }
  return results.filter(Boolean);
}

assert.deepEqual(
  await resources(),
  [],
  `Refusing to reuse existing resources for ${project}`,
);
let directory;
async function cleanup() {
  await docker([
    ...compose,
    "down",
    "--volumes",
    "--remove-orphans",
    "--timeout",
    "10",
  ]);
  assert.deepEqual(
    await resources(),
    [],
    "Cold-smoke cleanup left project resources",
  );
  console.log(`Cleanup verified: ${project}: 0 containers, networks, volumes.`);
}
async function state(service) {
  const id = (
    await docker([...compose, "ps", "--all", "--quiet", service], {
      capture: true,
    })
  ).output;
  if (!id) return null;
  return JSON.parse(
    (
      await docker(["inspect", "--format", "{{json .State}}", id], {
        capture: true,
      })
    ).output,
  );
}

try {
  await docker([
    ...compose,
    "build",
    "admin-smoke-backend-bootstrap",
    "admin-smoke-bootstrap",
    "admin-smoke-backend",
    "admin-smoke",
  ]);
  directory = await mkdtemp(join(tmpdir(), "agatceramic-cold-smoke-"));
  const override = join(directory, "failure.json");
  for (const [bootstrap, exitCode, blocked] of [
    [
      "admin-smoke-backend-bootstrap",
      23,
      ["admin-smoke-backend", "admin-smoke"],
    ],
    ["admin-smoke-bootstrap", 24, ["admin-smoke"]],
  ]) {
    // Stub successful setup and backend HTTP readiness only for failure
    // injection, so each dependency gate is exercised independently of installs.
    await writeFile(
      override,
      JSON.stringify({
        services: {
          "admin-smoke-backend-bootstrap": { command: ["sh", "-c", "exit 0"] },
          "admin-smoke-bootstrap": { command: ["sh", "-c", "exit 0"] },
          "admin-smoke-backend": {
            command: ["sleep", "60"],
            healthcheck: { test: ["CMD", "true"] },
          },
          [bootstrap]: { command: ["sh", "-c", `exit ${exitCode}`] },
        },
      }),
    );
    const failure = await docker(
      [
        ...compose,
        "-f",
        join(root, "compose.yaml"),
        "-f",
        override,
        "up",
        "-d",
        "admin-smoke",
      ],
      { allowFailure: true },
    );
    assert.notEqual(
      failure.code,
      0,
      "Failed bootstrap must block Compose startup",
    );
    assert.equal(
      (await state(bootstrap))?.ExitCode,
      exitCode,
      "Expected the injected bootstrap failure",
    );
    for (const service of blocked) {
      const current = await state(service);
      assert.ok(
        !current || current.Status === "created",
        `${service} started after ${bootstrap} failed`,
      );
    }
    console.log(
      `${bootstrap}: exit ${exitCode} blocked ${blocked.join(", ")}.`,
    );
    await cleanup();
  }

  const started = Date.now();
  // Allow PostgreSQL's 42s health budget, backend bootstrap (300s + 10s
  // termination grace), and both unchanged 65s HTTP readiness budgets. Admin's
  // 360s bootstrap runs in parallel; the 490s bound covers the longer path.
  await docker([
    ...compose,
    "up",
    "-d",
    "--wait",
    "--wait-timeout",
    "490",
    "admin-smoke",
  ]);
  console.log(
    `Clean-volume cold readiness: ${((Date.now() - started) / 1000).toFixed(1)}s.`,
  );
  for (const service of [
    "admin-smoke-backend-bootstrap",
    "admin-smoke-bootstrap",
  ]) {
    const current = await state(service);
    assert.equal(current?.Status, "exited", `${service} did not finish`);
    assert.equal(current.ExitCode, 0, `${service} failed`);
    console.log(
      `${service}: completed ${current.StartedAt} -> ${current.FinishedAt}`,
    );
  }
  for (const service of ["admin-smoke-backend", "admin-smoke"]) {
    const current = await state(service);
    assert.equal(
      current?.Health?.Status,
      "healthy",
      `${service} is not healthy`,
    );
  }
  await docker([
    ...compose,
    "exec",
    "-T",
    "admin-smoke-backend",
    "sh",
    "-ec",
    "test -s vendor/.agatceramic-lock-fingerprint && test -s /smoke-state/fixtures.json",
  ]);
  await docker([
    ...compose,
    "exec",
    "-T",
    "admin-smoke",
    "sh",
    "-ec",
    "test -s node_modules/.agatceramic-lock-fingerprint && test -s dist/index.html",
  ]);
  await docker([
    ...compose,
    "logs",
    "--no-color",
    "--timestamps",
    "admin-smoke-backend-bootstrap",
    "admin-smoke-bootstrap",
  ]);
  console.log("Admin cold-bootstrap regression passed.");
  if (process.argv.includes("--browser-smoke")) {
    await docker([...compose, "run", "--rm", "--build", "admin-smoke-e2e"]);
    console.log("Real Admin full-stack browser smoke passed.");
  }
  if (process.argv.includes("--composer-audit")) {
    const audit = await docker(
      [
        ...compose,
        "exec",
        "-T",
        "admin-smoke-backend",
        "timeout",
        "--kill-after=10s",
        "90s",
        "composer",
        "audit",
        "--locked",
      ],
      { allowFailure: true },
    );
    console.log(`Separate Composer security audit exit: ${audit.code}.`);
    if (audit.code !== 0) process.exitCode = audit.code ?? 1;
  }
} catch (error) {
  await docker([...compose, "logs", "--no-color", "--tail", "60"], {
    allowFailure: true,
  });
  throw error;
} finally {
  await cleanup();
  if (directory) {
    assert.equal(
      dirname(resolve(directory)),
      resolve(tmpdir()),
      "Cleanup must remain inside the temporary directory",
    );
    await rm(directory, { recursive: true, force: true });
  }
}
