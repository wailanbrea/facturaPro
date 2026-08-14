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
}
