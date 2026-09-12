import { deleteCategory, getCategories, getCategoryAttributeGroups, getCategoryAttributes, replaceCategoryAttributeGroups, replaceCategoryAttributes, saveCategory } from '../services/categories'
import { getAllAttributes } from '../../attributes/services/attributes'
import { getAllAttributeGroups } from '../../attribute-groups/services/attributeGroups'

/** Centralizes all category endpoint access for the route feature. */
export function useCategoryCatalog() {
  return { getCategories, getCategoryAttributes, getCategoryAttributeGroups, getAllAttributes, getAllAttributeGroups, replaceCategoryAttributes, replaceCategoryAttributeGroups, saveCategory, deleteCategory }
}
