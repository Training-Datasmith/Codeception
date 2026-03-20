# Codeception Architecture

## Purpose

A PHP testing framework that unifies unit, integration/functional, and acceptance
testing in a single BDD-inspired workflow using "Cest" and "Gherkin" scenarios.

## Directory Structure

```
src/Codeception/
  Events.php                        — event name constants for the event system
  Exception/
    Deprecation.php / Error.php / Notice.php / Warning.php
                                    — error-handler bridge exceptions
  Lib/Generator/
    Feature.php                     — generates Gherkin .feature file stubs
  Test/
    Data_Provider.php               — wraps PHPUnit data-provider tests
    Filter.php                      — filters tests by group, name, or tag
    Loader/
      Unit.php                      — discovers and loads PHPUnit-style unit tests
    Feature/
      Assertion_Counter.php         — tracks assertion count during a test run
      Code_Coverage.php             — enables xdebug/phpdbg code coverage
      Ignore_If_Metadata_Blocked.php — skips tests based on metadata conditions
      Metadata_Collector.php        — collects test metadata (groups, tags, etc.)
      Scenario_Loader.php           — loads Gherkin scenarios for Cest execution
  Util/
    Fixtures.php                    — global fixture store (key→value map)
ext/
  Simple_Reporter.php               — minimal output reporter extension
  Suite_Init_Subscriber_Trait.php   — trait for suite-level event subscribers
example/
  tests/Support/
    Acceptance_Tester.php / Functional_Tester.php / Unit_Tester.php
                                    — generated actor stubs
```

## Key Design Decisions

- **Event-driven**: the core test runner emits Symfony EventDispatcher events
  (defined in `Events.php`) at each lifecycle point (suite start/end, test
  start/fail/end), allowing modules and extensions to hook in non-invasively.
- **Actor pattern**: each test suite gets a generated "Tester" actor class
  (e.g. `AcceptanceTester`) that provides a fluent API; the actor delegates to
  loaded module classes behind the scenes.
- **Module system**: capabilities (browser interaction, ORM helpers, REST clients)
  are provided by modules loaded per-suite in `codeception.yml`.
- **Multi-format tests**: supports Cest (OOP scenario), Gherkin Feature, Cept
  (script-style), and standard PHPUnit test classes in a single framework.

## Extension Points

- Implement a custom module by extending `Codeception\Module`.
- Implement a custom extension by implementing `Codeception\Extension`.
- Subscribe to events by implementing `Codeception\Events` constants in an
  extension's `$events` property.

## Dependency Flow

```
CLI (codecept run)
  └── Application
        └── Runner (per suite)
              ├── Module chain (Browser, DB, REST, …)
              ├── EventDispatcher → Extension\*
              └── PHPUnit TestRunner → Cest / Unit / Gherkin tests
```
