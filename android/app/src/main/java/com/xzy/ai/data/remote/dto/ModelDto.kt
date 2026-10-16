package com.xzy.ai.data.remote.dto

import com.google.gson.annotations.SerializedName

data class ModelDto(
    val id: String,
    val name: String?,
    val available: Boolean,
    @SerializedName("context_window")
    val contextWindow: Int?,
    val pricing: ModelPricing?
)

data class ModelPricing(
    @SerializedName("input_per_1m")
    val inputPer1M: Double?,
    @SerializedName("output_per_1m")
    val outputPer1M: Double?
)

data class ModelsResponse(
    val data: List<ModelDto>,
    val source: String // "live" or "fallback"
)
