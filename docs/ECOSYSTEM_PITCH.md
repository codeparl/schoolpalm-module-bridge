  SchoolPalm Architecture Overview | Modular Educational OS \* { margin: 0; padding: 0; box-sizing: border-box; } body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; line-height: 1.6; color: #1a1e24; background-color: #f9fafb; padding: 2rem 1rem; } .container { max-width: 1200px; margin: 0 auto; background: white; border-radius: 1rem; box-shadow: 0 4px 20px rgba(0,0,0,0.05); padding: 2rem 2rem 3rem; } h1 { font-size: 2.5rem; font-weight: 700; margin-bottom: 0.5rem; color: #0f172a; border-bottom: 3px solid #3b82f6; display: inline-block; padding-bottom: 0.25rem; } h2 { font-size: 1.8rem; font-weight: 600; margin: 2rem 0 1rem 0; color: #1e293b; padding-left: 0.5rem; border-left: 4px solid #3b82f6; } h3 { font-size: 1.4rem; font-weight: 600; margin: 1.5rem 0 0.75rem 0; color: #334155; } h4 { font-size: 1.2rem; font-weight: 600; margin: 1rem 0 0.5rem 0; color: #475569; } p { margin-bottom: 1rem; color: #334155; } .note { background: #eef2ff; border-left: 4px solid #3b82f6; padding: 1rem 1.5rem; margin: 1.5rem 0; border-radius: 0.5rem; font-style: normal; color: #1e293b; } table { width: 100%; border-collapse: collapse; margin: 1.5rem 0; background: white; border-radius: 0.5rem; overflow: hidden; box-shadow: 0 1px 2px rgba(0,0,0,0.05); } th, td { border: 1px solid #e2e8f0; padding: 0.75rem 1rem; text-align: left; } th { background-color: #f1f5f9; font-weight: 600; color: #0f172a; } code { background: #f1f5f9; padding: 0.2rem 0.4rem; border-radius: 0.25rem; font-family: 'SF Mono', 'Fira Code', monospace; font-size: 0.9rem; color: #0f172a; } pre { background: #1e293b; color: #e2e8f0; padding: 1rem 1.5rem; border-radius: 0.5rem; overflow-x: auto; margin: 1.5rem 0; font-family: 'SF Mono', 'Fira Code', monospace; font-size: 0.9rem; } pre code { background: none; color: inherit; padding: 0; } ul, ol { margin: 1rem 0 1rem 1.8rem; } li { margin-bottom: 0.5rem; } .mermaid { background: #f8fafc; padding: 1rem; border-radius: 0.75rem; margin: 1.5rem 0; text-align: center; } hr { margin: 2rem 0; border: none; height: 1px; background: #e2e8f0; } footer { margin-top: 3rem; text-align: center; font-size: 0.875rem; color: #64748b; border-top: 1px solid #e2e8f0; padding-top: 2rem; } @media (max-width: 768px) { .container { padding: 1.5rem; } h1 { font-size: 1.9rem; } h2 { font-size: 1.5rem; } }

Architecture Overview
=====================

Understanding SchoolPalm's modular architecture, the Module Bridge runtime engine, and the relationship between Core, SDK, and modules.

1\. Introduction
----------------

SchoolPalm is a modular Educational Operating System (EOS) designed for schools. Unlike monolithic school management systems, SchoolPalm treats every feature—Students, Fees, Library, Attendance, etc.—as an independent **module** that can be installed, updated, or removed without affecting the core platform.

This architecture overview describes the four fundamental components of the SchoolPalm ecosystem, how they interact, and the design principles that ensure consistency, reliability, and developer productivity.

2\. Ecosystem Components at a Glance
------------------------------------

Component

Role

Environment

**SchoolPalm Core**

Production system that runs live school data

Production

**Module Bridge**

Shared runtime engine that defines execution rules

Used by both Core and SDK

**Module SDK**

Development sandbox for building and testing modules

Development / Testing

**Modules**

Independent feature packages (business logic + UI)

Any environment

🔑 **Key insight:** Core and SDK never run modules directly. Both delegate to the **Module Bridge**, which provides a stable, contract-driven execution layer.

3\. High-Level Architecture Diagram
-----------------------------------

flowchart TB subgraph Production Core\[SchoolPalm Core  
Production System\] end subgraph Development SDK\[Module SDK  
Development Sandbox\] end subgraph SharedRuntime Bridge\[Module Bridge  
Runtime Engine\] end subgraph Modules M1\[Students Module\] M2\[Fees Module\] M3\[Library Module\] end Core -->|uses| Bridge SDK -->|uses| Bridge Bridge -->|loads & executes| Modules

**Data flow note:** The Module Bridge is not a separate service; it is a library (set of PHP contracts and runtime bindings) embedded into both Core and SDK. However, conceptually it acts as a shared abstraction layer.

4\. Detailed Component Descriptions
-----------------------------------

### 4.1 SchoolPalm Core (Production System)

*   **Purpose:** Runs the live school environment with real users, real data, and real integrations.
*   **Responsibilities:** Hosts the Module Bridge, discovers installed modules via registry, routes HTTP requests to module actions, manages infrastructure (database, storage, auth), and executes lifecycle operations (install/upgrade/uninstall) using the bridge’s pipeline.
*   **Does NOT do:** Contain module-specific business logic, UI templates, or action definitions. Those live inside each module.

### 4.2 Module Bridge (Core Runtime Engine)

The Module Bridge is the **central contract and execution engine** of the entire ecosystem. It provides:

*   **Base Module Contract:** Abstract class `SchoolPalm\ModuleBridge\Core\Module` that all modules must extend.
*   **Runtime Binding Mechanism:** Uses `class_alias` to bind host’s concrete base module to the contract, enabling same module code to run in Core or SDK.
*   **Action Resolver Contract:** Defines route-to-action mapping.
*   **Relation Subsystem:** Module declaration and hydration of relationships across environments.
*   **Contract Query Builder:** Fluent, Laravel-style query abstraction (`ContractQueryBuilder`) without tying to specific database/ORM.
*   **Installer Pipeline:** Orchestrates validation, registration, snapshotting, asset preparation.

📦 The bridge is **not** a standalone service—it is embedded in both Core and SDK, ensuring identical execution rules.

### 4.3 Module SDK (Development Sandbox)

*   **Purpose:** Safe, isolated environment for module development and testing.
*   **Responsibilities:** Includes same Module Bridge version, simulates Core runtime, offers scaffolding/validation commands, never runs production data.
*   **Why not develop inside Core?** Prevents accidental damage, allows rapid iteration, catches contract violations early.

### 4.4 Modules

*   **Definition:** Independent feature packages extending SchoolPalm’s functionality.
*   **Structure:** Must extend `Module` abstract class and implement required methods (`registerActions()`, `registerRelations()`).
*   **Contents:** UI components, action classes, business logic, migrations, config.
*   **Installation:** Via bridge’s installer pipeline; can be enabled/disabled/removed without recompiling core.
*   **Examples:** Students Management, Fees Collection, Library Circulation, Attendance Tracking.

5\. Module Bridge Deep Dive
---------------------------

### 5.1 Runtime Binding via `class_alias`

All modules extend `SchoolPalm\ModuleBridge\Core\Module`. Host application calls:

    use SchoolPalm\ModuleBridge\Support\Bridge;
    
    Bridge::bind(ConcreteBaseModule::class);

The bridge uses `class_alias` to alias the host’s concrete base module to the abstract contract. Result: the same compiled module works in Core and SDK.

### 5.2 Action Resolution Contract

    interface ActionResolver {
        public function resolve(string $moduleName, string $actionPath): Action;
    }

Core and SDK provide their own implementations; modules stay agnostic.

### 5.3 Relation Subsystem

    class StudentModule extends Module {
        public function registerRelations(RelationRegistry $registry): void {
            $registry->hasMany(FeeModule::class, 'student_id');
        }
    }

Bridge builds unified registry and hydration engine, working identically in Core (SQL) and SDK (SQLite/in-memory).

### 5.4 Contract Query Builder

    $query = ContractQueryBuilder::for(StudentModule::class)
        ->where('grade', '>=', 10)
        ->with('fees')
        ->orderBy('name');
    
    $students = $query->get();

Query stores only intent; execution delegated to host’s `querySearch` service. Decouples modules from ORM while offering fluent syntax.

### 5.5 Installer Pipeline

Standardised stages: **Validation → Registration → Database → Asset Publishing → Snapshot**. Pipeline orchestration lives in bridge (`src/Pipeline/*`), ensuring Core and SDK process modules identically.

6\. Runtime Execution Flow (Action System)
------------------------------------------

sequenceDiagram participant User participant Core as SchoolPalm Core participant Bridge as Module Bridge participant Module as Specific Module User->>Core: GET /students/list?grade=10 Core->>Bridge: Route to module & action Bridge->>Bridge: Resolve module (e.g., Students) Bridge->>Module: Instantiate module Bridge->>Module: Call action "list" with params Module->>Bridge: Use ContractQueryBuilder Bridge->>Core: Delegate query execution Core-->>Bridge: Return hydrated data Bridge-->>Module: Return results as contracts Module-->>Bridge: Return view / response Bridge-->>Core: Finalise HTTP response Core-->>User: HTML / JSON response

*   Bridge resolves module and action but contains no business logic.
*   Modules use query abstraction; host executes actual database queries.
*   Relations hydrated by bridge before module receives results.

7\. Module Development with the SDK
-----------------------------------

The Module SDK replicates Core’s runtime environment, enabling developers to:

1.  Scaffold a module: `php artisan module:make Students`.
2.  Implement actions, relations, UI inside module directory.
3.  Test locally: `http://sdk.test/students/list`.
4.  Validate contract: `php artisan module:validate`.
5.  Package module (ZIP/PHAR).

Because SDK uses the identical Module Bridge library, any module working in SDK works identically in Core.

8\. Registry and Pipeline Systems
---------------------------------

### 8.1 Dynamic Module Registry

Stores installed module names, versions, actions, relations, timestamps, snapshots. Registry API identical across Core and SDK, though backends may differ (database vs file).

### 8.2 Lifecycle Pipeline

*   **Install** → Validate → Register → Migrate → Publish Assets → Snapshot
*   **Upgrade** → Validate version → Apply upgrade script → Update registry
*   **Uninstall** → Validate dependencies → Revert snapshot → Remove registry entry

Orchestrated by `InstallerPipeline`; host provides concrete implementations for file system, database, asset publishing.

9\. Benefits for Stakeholders
-----------------------------

Stakeholder

Benefit

School Administrators

Install only needed features; update modules independently.

Module Developers

Write modules once, test in SDK, confident they run identically in Core.

Core Platform Team

Evolve infrastructure without breaking existing modules, as long as bridge contract is stable.

Quality Assurance

Test modules in isolation using SDK; behavioural parity guaranteed.

System Integrators

Integrate external services at module level without touching Core.

10\. Conclusion
---------------

SchoolPalm’s architecture separates concerns cleanly:

*   **Core** provides production infrastructure.
*   **Module Bridge** provides stable contract and execution engine.
*   **Module SDK** provides safe development sandbox.
*   **Modules** provide business value.

The Module Bridge is the linchpin. By abstracting runtime binding, action resolution, relations, queries, and pipeline orchestration, it ensures that modules are truly portable between development and production environments. This design reduces integration bugs, accelerates module development, and allows the entire ecosystem to evolve without fragmentation.

For technical implementors, the bridge’s source code (`src/Support/Bridge.php`, `src/Query/ContractQueryBuilder.php`, `src/Relations/`, `src/Pipeline/`) provides further detail on the mechanisms described above.

SchoolPalm – Modular Education Operating System

