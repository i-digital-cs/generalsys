Login with the seeded accounts (password for all: password):                              

   ┌───────────────────┬───────────────┬────────────────────────────────────────────────────┐
   │ Account           │ Role          │ What you should see                                │
   ├───────────────────┼───────────────┼────────────────────────────────────────────────────┤
   │ admin@hrpro.test  │ Manager       │ Admin dashboard, Employees CRUD, rates, documents, │
   │                   │               │ settings                                           │
   ├───────────────────┼───────────────┼────────────────────────────────────────────────────┤
   │ leader@hrpro.test │ Team Leader   │ Generic dashboard, no money/salary anywhere;       │
   │                   │               │ /admin/... URLs → 403                              │
   ├───────────────────┼───────────────┼────────────────────────────────────────────────────┤
   │ radwa@hrpro.test  │ Employee      │ Own read-only profile with contract & documents    │
   │                   │ (EGP)         │                                                    │
   ├───────────────────┼───────────────┼────────────────────────────────────────────────────┤
   │ mona@hrpro.test   │ Employee      │ Own read-only profile                              │
   │                   │ (AED)         │                                                    │
   └───────────────────┴───────────────┴────────────────────────────────────────────────────┘