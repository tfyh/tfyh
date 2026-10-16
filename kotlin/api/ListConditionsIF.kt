package org.tfyh.api

interface ListConditionsIF {
    fun getFurtherConditions(listName: String): Map<String, String>
}