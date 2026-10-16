package com.xzy.ai.data.remote.dto

import com.google.gson.annotations.SerializedName

data class ProjectDto(
    val id: String,
    val name: String,
    val framework: String?,
    val language: String?,
    @SerializedName("created_at")
    val createdAt: String
)

data class ProjectsResponse(
    val data: List<ProjectDto>
)

data class CreateProjectRequest(
    val name: String,
    val framework: String?,
    val language: String?
)
