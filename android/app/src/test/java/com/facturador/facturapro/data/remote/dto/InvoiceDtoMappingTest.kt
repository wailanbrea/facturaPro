package com.facturador.facturapro.data.remote.dto

import com.google.gson.Gson
import org.junit.Assert.assertEquals
import org.junit.Test

class InvoiceDtoMappingTest {
    @Test
    fun detail_preserves_document_client_contact_snapshot() {
        val dto = Gson().fromJson(
            """
            {
              "id": 7,
              "document_type": "invoice",
              "invoice_date": "2026-08-13",
              "payment_term_id": 1,
              "client_name": "Cliente",
              "client_tax_id": "B123",
              "client_address": "Calle 1",
              "client_city": "Barcelona",
              "client_phone": "+34 600 000 000",
              "client_email": "documento@example.com",
              "currency_id": 1,
              "currency_code": "EUR",
              "currency_symbol": "€",
              "amount_received": "0.0000",
              "subtotal": "100.0000",
              "tax_total": "21.0000",
              "total": "121.0000",
              "balance_due": "121.0000",
              "status": "draft",
              "items": []
            }
            """.trimIndent(),
            InvoiceDto::class.java,
        )

        val detail = dto.toDetail()

        assertEquals("Barcelona", detail.clientCity)
        assertEquals("+34 600 000 000", detail.clientPhone)
        assertEquals("documento@example.com", detail.clientEmail)
    }

    @Test
    fun detail_preserves_intervention_diagnostic_and_technical_conclusions() {
        val dto = Gson().fromJson(
            """
            {
              "id": 8,
              "document_type": "invoice",
              "invoice_date": "2026-08-15",
              "payment_term_id": 1,
              "client_name": "Cliente Climatización",
              "currency_id": 1,
              "currency_code": "EUR",
              "currency_symbol": "€",
              "amount_received": "0.0000",
              "subtotal": "250.0000",
              "tax_total": "52.5000",
              "total": "302.5000",
              "balance_due": "302.5000",
              "status": "draft",
              "intervention": {
                "equipment_type": "Split Conductos",
                "equipment_brand": "Daikin",
                "equipment_model": "FBQ100C",
                "diagnostic_summary": "Falta de refrigerante R32 por fuga en abocardado",
                "technical_conclusions": "Reparado abocardado, realizada prueba de estanqueidad y recarga de 1.2kg"
              },
              "items": []
            }
            """.trimIndent(),
            InvoiceDto::class.java,
        )

        val detail = dto.toDetail()

        assertEquals("Split Conductos", detail.intervention?.equipmentType)
        assertEquals("Daikin", detail.intervention?.equipmentBrand)
        assertEquals("FBQ100C", detail.intervention?.equipmentModel)
        assertEquals("Falta de refrigerante R32 por fuga en abocardado", detail.intervention?.diagnosticSummary)
        assertEquals("Reparado abocardado, realizada prueba de estanqueidad y recarga de 1.2kg", detail.intervention?.technicalConclusions)
    }

    @Test
    fun draft_to_remote_serializes_intervention_diagnostic_and_conclusions() {
        val draft = com.facturador.facturapro.domain.model.InvoiceDraft(
            documentType = "invoice",
            invoiceDate = "2026-08-15",
            paymentTermId = 1L,
            clientId = 10L,
            clientName = "Empresa Cliente",
            clientTaxId = "B99999999",
            clientAddress = "Av. Principal 123",
            clientCity = "Madrid",
            clientPhone = "912345678",
            clientEmail = "contacto@empresa.es",
            currencyId = 1L,
            fiscalProfileId = 1L,
            logoPath = null,
            bankAccountId = null,
            warrantyId = 1L,
            warrantyText = "Garantía estándar",
            legalText = null,
            conformityText = null,
            observations = null,
            amountReceived = "0",
            preparedBy = "Técnico Juan",
            receivedBy = "Cliente",
            intervention = com.facturador.facturapro.domain.model.Intervention(
                equipmentType = "Bomba de calor",
                diagnosticSummary = " Compresor bloqueado por suciedad en batería exterior ",
                technicalConclusions = " Limpieza con hidro y comprobación de presiones operativas. Equipo OK. "
            ),
            items = emptyList(),
        )

        val remote = draft.toRemote()

        assertEquals("Bomba de calor", remote.intervention?.equipmentType)
        assertEquals("Compresor bloqueado por suciedad en batería exterior", remote.intervention?.diagnosticSummary)
        assertEquals("Limpieza con hidro y comprobación de presiones operativas. Equipo OK.", remote.intervention?.technicalConclusions)
    }

    @Test
    fun quotation_with_thirteen_services_sends_every_item_to_the_backend() {
        val draft = com.facturador.facturapro.domain.model.InvoiceDraft(
            documentType = "quotation",
            invoiceDate = "2026-08-25",
            paymentTermId = 1L,
            clientId = 10L,
            clientName = "Cliente",
            clientTaxId = null,
            clientAddress = null,
            clientCity = null,
            clientPhone = null,
            clientEmail = null,
            currencyId = 1L,
            fiscalProfileId = 1L,
            logoPath = "logos/logo_tu_tecnico_autorizado.png",
            bankAccountId = 1L,
            warrantyId = 1L,
            warrantyText = null,
            legalText = null,
            conformityText = null,
            observations = null,
            amountReceived = null,
            preparedBy = null,
            receivedBy = null,
            items = (1..13).map { index ->
                com.facturador.facturapro.domain.model.InvoiceDraftItem(
                    description = "Servicio $index",
                    quantity = "1",
                    unitCost = "100.00",
                    taxId = 1L,
                )
            },
        )

        val remote = draft.toRemote()

        assertEquals("quotation", remote.documentType)
        assertEquals(13, remote.items.size)
        assertEquals((1..13).map { "Servicio $it" }, remote.items.map { it.description })
    }
}
