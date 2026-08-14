package com.facturador.facturapro.data.remote.dto

import com.google.gson.Gson
import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Test

class SettingsDtoMappingTest {
    @Test
    fun bootstrap_preserves_bank_profile_and_locked_fields() {
        val dto = Gson().fromJson(
            """
            {
              "currencies": [],
              "taxes": [],
              "payment_terms": [],
              "warranties": [],
              "bank_accounts": [{
                "id": 9,
                "label": "Cuenta Pamela",
                "account_holder": "Pamela",
                "fiscal_profile_id": 2,
                "account_type": "official"
              }],
              "fiscal_profiles": [],
              "legal_texts": [],
              "invoice_number_settings": [],
              "invoice_locked_fields": ["observations"]
            }
            """.trimIndent(),
            BootstrapDto::class.java,
        )

        val catalogs = dto.toDomain()

        assertEquals(2L, catalogs.bankAccounts.single().fiscalProfileId)
        assertTrue("observations" in catalogs.lockedInvoiceFields)
    }
}
