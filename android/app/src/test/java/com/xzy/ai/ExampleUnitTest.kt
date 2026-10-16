package com.xzy.ai

import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Test

class ExampleUnitTest {
    @Test
    fun addition_isCorrect() {
        assertEquals(4, 2 + 2)
    }

    @Test
    fun app_name_is_valid() {
        val appName = "XZY AI"
        assertTrue(appName.isNotEmpty())
    }
}
