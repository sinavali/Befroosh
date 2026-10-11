---
id: product:befroosh/flows/ticket-support-and-escalation
parent: product:befroosh/flows
title: Flow 10 & 11: Support Ticketing & SuperAdmin VIP Escalation
level: product
kind: blueprint
domains: [support, orders, multi-tenancy]
flows: [support-ticket-escalation]
keywords: [flow, support, tickets, escalation, ultimate-tier, vip-sla]
references: [product:befroosh/cross-cutting/subscription-quotas]
code_paths: [src/Application/UseCases/MultiTenancy/EscalateTicketHandler.php, routes_tickets.php]
test_paths: [tests/test_application_usecases.php]
---

# Flow 10 & 11: Support Ticketing & SuperAdmin VIP Escalation

## Purpose
Provides threaded communication between customers and merchant staff with file attachments and linked orders, with emergency SuperAdmin SLA escalation for Ultimate-tier merchants.

## Participants
- `support`: Tickets, threaded messages, file attachments up to 10MB
- `orders`: Optional contextual order association
- `multi-tenancy`: Subscription tier verification for emergency escalation

## Steps
1. Customer submits a support inquiry at `/tickets/create`, optionally linking an Order UUID and attaching files.
2. Merchant staff answers via `/tickets/{id}`.
3. If issue is critical and tenant is on the **Ultimate** subscription tier, merchant clicks "Escalate to SuperAdmin Emergency Queue".
4. `EscalateTicketHandler` validates `shop.plan.has_ticket_escalation == 1` (`SubscriptionLimitExceededException` otherwise).
5. Ticket is elevated to `is_escalated = 1` and appears in the SuperAdmin priority triage queue at `/admin/plans`.
