import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { usePaginatedCollection } from '../../../composables/usePaginatedCollection'
import type { Attribute } from '../../attributes/types/attribute.types'
import type { AttributeGroup } from '../../attribute-groups/types/attributeGroup.types'
import { getAllBrands } from '../../brands/services/brands'
import type { Brand } from '../../brands/types/brand.types'
import { getCategories, getCategoryAttributeGroups } from '../../categories/services/categories'
import type { Category } from '../../categories/types/category.types'
import { getCategoryAttributes, getProductAttributeValues, saveProductAttributeValues } from '../services/productAttributes'
import { deleteProductGroup, getAllProductGroups, ProductGroupRequestError, saveProductGroup } from '../services/productGroups'
import { deleteProductImage, getAllProductImages, updateProductImage, uploadProductImage } from '../services/productImages'
import { getProductRelations, getRelationCandidates, ProductRelationRequestError, saveProductRelations } from '../services/productRelations'
import { deleteProduct, getProductExport, getProducts, saveProduct } from '../services/products'
import { useAuthStore } from '../../../stores/auth'
import { compareAlphabetically, sortByLabel } from '../../../utils/alphabetical'
import { useProductEditorSteps } from './useProductEditorSteps'
import { normalizedProductName, productSlug } from '../validation/product.schema'
import type { AttributeDraftValue, Product, ProductFilters, ProductGroup, ProductGroupPayload, ProductImage, ProductPayload, ProductRelationDraft, ProductSort, ProductUnit, SortDirection } from '../types/product.types'

export function useProductEditor() {
const auth = useAuthStore(); const list = usePaginatedCollection<Product>('Не удалось загрузить товары.')
const { items: products, pagination, error, loading } = list
const categories = ref<Category[]>([]); const brands = ref<Brand[]>([]); const candidates = ref<Product[]>([]); const groupProducts = ref<ProductGroup['products']>([])
const opened = ref(false); const saving = ref(false); const editing = ref<Product | null>(null); const deleting = ref<Product | null>(null); const { activeStep, reset: resetStep, steps } = useProductEditorSteps(); const success = ref('')
const confirmError = ref('')
const filterResultStatus = ref('')
const exportStatus = ref('')
const exporting = ref(false)
const importOpened = ref(false)
const priceStatusImportOpened = ref(false)
const groupImportOpened = ref(false)
const productCountUnavailable = ref(false)
const filters = ref({ search: '', category_id: '', brand_id: '', is_active: '', is_on_sale: '' })
const sort = ref<ProductSort>('created_at'); const direction = ref<SortDirection>('desc')
const form = ref<ProductPayload>(emptyProduct()); const manuallyEditedSlug = ref(false); const copiedFromProduct = ref<Product | null>(null); const copiedNameError = ref('')
const attributes = ref<Attribute[]>([]); const attributeGroups = ref<AttributeGroup[]>([]); const attributeValues = ref<Record<number, AttributeDraftValue>>({}); const requiredIds = ref<number[]>([])
const images = ref<ProductImage[]>([]); const draggedImageId = ref<number|null>(null); const draggedOverImageId = ref<number|null>(null); const selectedFile = ref<File | null>(null); const imageInput = ref<HTMLInputElement | null>(null); const imageDeleting = ref<ProductImage|null>(null); const imageStatus = ref('')
const groups = ref<ProductGroup[]>([]); const selectedGroupId = ref(''); const groupErrors = ref<Record<string, string[]>>({}); const groupForm = ref<ProductGroupPayload>({ name: '', code: '', axis_attribute_ids: [], product_ids: [] }); const groupSearch=ref(''); const groupDeleting=ref(false)
const relations = ref<ProductRelationDraft[]>([]); const relationErrors = ref<Record<number, string>>({}); const relationSearch=ref('')
const canManage = computed(() => auth.hasPermission('catalog.manage'))
const canManageImports = computed(() => auth.hasPermission('imports.manage'))
const sortLabels: Record<ProductSort, string> = { sku: 'SKU', name: 'наименованию', created_at: 'дате создания', updated_at: 'дате изменения' }
const sortStatus = computed(() => `Сортировка по ${sortLabels[sort.value]}, ${direction.value === 'asc' ? 'по возрастанию' : 'по убыванию'}.`)
const productDateFormatter = new Intl.DateTimeFormat('ru-RU', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
const copiedFromName = computed(() => copiedFromProduct.value?.name ?? null)
const units: Array<{ value: ProductUnit; label: string }> = sortByLabel([{ value:'piece',label:'Штука'},{value:'square_meter',label:'Квадратный метр'},{value:'linear_meter',label:'Погонный метр'},{value:'package',label:'Упаковка'},{value:'kilogram',label:'Килограмм'},{value:'liter',label:'Литр'},{value:'set',label:'Комплект'}])
const relationTypes = sortByLabel([{ value: 'related', label: 'Сопутствующий товар' }, { value: 'recommended', label: 'Рекомендуемый товар' }])
const activityOptions=[{value:'',label:'Все'},{value:'1',label:'Активные'},{value:'0',label:'Скрытые'}]
const saleOptions=[{value:'',label:'Все'},{value:'1',label:'Распродажа'},{value:'0',label:'Не распродажа'}]
function flatten(nodes: Category[], depth=0): Array<Category & {depth:number}> { return [...nodes].sort((left,right)=>compareAlphabetically(left.name,right.name)).flatMap(item => [{...item,depth},...flatten(item.children??[],depth+1)]) }
const categoryOptions = computed(() => flatten(categories.value).filter(item=>item.is_active).map(item=>({value:String(item.id),label:`${'— '.repeat(item.depth)}${item.name}`})))
const brandOptions = computed(() => [{value:'',label:'Без бренда'},...sortByLabel(brands.value.map(item=>({value:String(item.id),label:item.name})))]); const filterCategoryOptions=computed(()=>[{value:'',label:'Все категории'},...categoryOptions.value]); const filterBrandOptions=computed(()=>[{value:'',label:'Все бренды'},...brandOptions.value.slice(1)])
const groupedAttributeSections=computed(()=>attributeGroups.value.map(group=>({...group,attributes:attributes.value.filter(attribute=>attribute.attribute_group_id===group.id)})).filter(group=>group.attributes.length))
const groupedAttributeIds=computed(()=>new Set(groupedAttributeSections.value.flatMap(group=>group.attributes.map(attribute=>attribute.id))))
const ungroupedAttributes=computed(()=>attributes.value.filter(attribute=>!groupedAttributeIds.value.has(attribute.id)))
const attributeSections=computed(()=>[...groupedAttributeSections.value,...(ungroupedAttributes.value.length?[{id:'ungrouped',name:'Без группы',attributes:ungroupedAttributes.value}]:[])])
const selectedCategory=computed({get:()=>form.value.category_id?String(form.value.category_id):'',set:value=>form.value.category_id=Number(value)}); const selectedBrand=computed({get:()=>form.value.brand_id===null?'':String(form.value.brand_id),set:value=>form.value.brand_id=value?Number(value):null})
const groupOptions=computed(()=>[{value:'',label:'Новая группа'},...sortByLabel(groups.value.map(group=>({value:String(group.id),label:`${group.name} · ${group.code}`})))]); const currentGroup=computed(()=>groups.value.find(group=>group.products.some(product=>product.id===editing.value?.id))??null)
const selectableCandidates=computed(()=>sortByLabel(candidates.value.filter(product=>!relations.value.some(row=>Number(row.related_product_id)===product.id)).map(product=>({value:String(product.id),label:`${product.name} · ${product.sku}`}))))
const hasActiveFilters=computed(()=>Object.values(filters.value).some(Boolean))
function slugify(value:string){return productSlug(value)}
function normalizedName(value:string){return normalizedProductName(value)}
const productCountLabel=computed(()=>{
  if(loading.value)return 'Обновляем количество товаров…'
  if(productCountUnavailable.value)return 'Количество товаров недоступно'
  const total=pagination.value?.total
  if(total===undefined)return 'Обновляем количество товаров…'
  return hasActiveFilters.value?`Найдено товаров: ${total}`:`Всего товаров в каталоге: ${total}`
})
function relationOptions(selectedId:string){return sortByLabel([...selectableCandidates.value,...candidates.value.filter(product=>String(product.id)===selectedId).map(product=>({value:String(product.id),label:`${product.name} · ${product.sku}`}))])}
function emptyProduct():ProductPayload{return{category_id:0,brand_id:null,name:'',slug:'',description:'',article_number:null,barcode:null,unit:'piece',price:'0.00',old_price:null,stock_quantity:0,is_active:false,is_on_sale:false}}
function toPayload(p:Product):ProductPayload{return{category_id:p.category_id,brand_id:p.brand_id,name:p.name,slug:p.slug,description:p.description??'',article_number:p.article_number,barcode:p.barcode,unit:p.unit,price:p.price,old_price:p.old_price,stock_quantity:p.stock_quantity,is_active:p.is_active,is_on_sale:p.is_on_sale}}
function inputValue(v:unknown):AttributeDraftValue{if(Array.isArray(v))return v.map(String);if(typeof v==='boolean')return v?'true':'false';return String(v??'')}
function attributeLabel(attribute:Attribute):string{return `${attribute.name}${attribute.unit?` (${attribute.unit})`:''}`}
function displayAttributeValue(attribute:Attribute|undefined,value:unknown):string{
  if(attribute?.type==='select')return attribute.options.find(option=>option.value===String(value))?.label??String(value??'')
  if(typeof value==='boolean')return value?'Да':'Нет'
  if(Array.isArray(value))return value.map(item=>String(item)).join(', ')
  return String(value??'')
}
function hasValue(v:AttributeDraftValue|undefined){return Array.isArray(v)?v.length>0:String(v??'').trim()!==''}
function typed(a:Attribute,v:AttributeDraftValue):string|number|boolean|string[]{if(a.type==='integer')return Number.parseInt(String(v),10);if(a.type==='decimal')return Number(v);if(a.type==='boolean')return v==='true';if(a.type==='multiselect')return Array.isArray(v)?v:[];return String(v)}
function enabled(step:string){return step==='main'||editing.value!==null}
function filtersPayload():ProductFilters{return{search:filters.value.search.trim()||undefined,category_id:filters.value.category_id?Number(filters.value.category_id):undefined,brand_id:filters.value.brand_id?Number(filters.value.brand_id):undefined,is_active:filters.value.is_active===''?undefined:filters.value.is_active==='1',is_on_sale:filters.value.is_on_sale===''?undefined:filters.value.is_on_sale==='1',sort:sort.value,direction:direction.value}}
async function fetchPage(page:number){const [productPage,categoryList,brandList]=await Promise.all([getProducts({...filtersPayload(),page}),getCategories(),getAllBrands()]);return{...productPage,categoryList,brandList}}
async function load(page=pagination.value?.current_page??1):Promise<boolean>{filterResultStatus.value='';productCountUnavailable.value=false;const response=await list.load(page,fetchPage);if(!response){if(error.value)productCountUnavailable.value=true;return false}categories.value=response.categoryList;brands.value=response.brandList;const from=response.meta.from??0;const to=response.meta.to??0;filterResultStatus.value=response.meta.total===0?'Товары не найдены.':`${hasActiveFilters.value?'Найдено товаров':'Всего товаров в каталоге'}: ${response.meta.total}. Показано ${from}–${to}.`;return true}
async function changeSort(field:ProductSort){if(loading.value)return;const previousSort=sort.value;const previousDirection=direction.value;if(sort.value===field)direction.value=direction.value==='asc'?'desc':'asc';else{sort.value=field;direction.value=field==='created_at'||field==='updated_at'?'desc':'asc'}if(!await load(1)){sort.value=previousSort;direction.value=previousDirection}}
function ariaSort(field:ProductSort):'none'|'ascending'|'descending'{return sort.value===field?(direction.value==='asc'?'ascending':'descending'):'none'}
function formatDate(value:string):string{return productDateFormatter.format(new Date(value))}
function isProductNameTruncated(value:string):boolean{return Array.from(value).length>50}
function productNamePreview(value:string):string{const symbols=Array.from(value);return symbols.length>50?`${symbols.slice(0,49).join('')}…`:value}
function resetFilters(){filters.value={search:'',category_id:'',brand_id:'',is_active:'',is_on_sale:''}}
async function exportFilteredProducts(){
  if(exporting.value)return
  const exportedFilteredSelection=hasActiveFilters.value
  const exportedFilters=filtersPayload()
  exporting.value=true;error.value='';exportStatus.value=''
  try{
    const file=await getProductExport(exportedFilters)
    const url=URL.createObjectURL(file.blob)
    const link=document.createElement('a')
    link.href=url;link.download=file.filename;document.body.appendChild(link);link.click();link.remove();URL.revokeObjectURL(url)
    exportStatus.value=exportedFilteredSelection?'Отфильтрованные товары экспортированы в Excel.':'Все товары экспортированы в Excel.'
  }catch(reason){error.value=reason instanceof Error?reason.message:'Не удалось экспортировать товары.'}
  finally{exporting.value=false}
}
function fillGroup(group:ProductGroup|null,id:number){selectedGroupId.value=group?String(group.id):'';groupForm.value=group?{name:group.name,code:group.code,axis_attribute_ids:group.axes.map(a=>a.id),product_ids:group.products.map(p=>p.id)}:{name:'',code:'',axis_attribute_ids:[],product_ids:[id]}}
function prepareGroupDraft(product:Product,groupList:ProductGroup[]){
  const ownGroup=groupList.find(group=>group.products.some(item=>item.id===product.id))??null
  if(ownGroup){groupProducts.value=ownGroup.products;fillGroup(ownGroup,product.id);return}
  const source=copiedFromProduct.value
  const compatible=source&&source.category_id===product.category_id&&source.brand_id===product.brand_id
  if(!source||!compatible){groupProducts.value=[product];fillGroup(null,product.id);return}
  const sourceGroup=groupList.find(group=>group.products.some(item=>item.id===source.id))??null
  if(sourceGroup){groupProducts.value=[...sourceGroup.products,product];fillGroup(sourceGroup,product.id);groupForm.value.product_ids=[...groupForm.value.product_ids,product.id];return}
  groupProducts.value=[source,product];fillGroup(null,product.id);groupForm.value.product_ids=[source.id,product.id]
}
function copyAttributeDrafts(values:Record<number,AttributeDraftValue>){return Object.fromEntries(Object.entries(values).map(([id,value])=>[id,Array.isArray(value)?[...value]:value]))}
async function loadDetails(product:Product,copiedAttributes:Record<number,AttributeDraftValue>|null=null){const [categoryAttrs,categoryAttributeGroups,savedAttrs,photoList,relationList,groupList,candidateList]=await Promise.all([getCategoryAttributes(product.category_id),getCategoryAttributeGroups(product.category_id),getProductAttributeValues(product.id),getAllProductImages(product.id),getProductRelations(product.id),getAllProductGroups(),getRelationCandidates(product.id)])
  attributes.value=categoryAttrs;attributeGroups.value=categoryAttributeGroups;requiredIds.value=categoryAttrs.filter(a=>a.is_required).map(a=>a.id);const saved=new Map(savedAttrs.map(v=>[v.attribute_id,v.value]));attributeValues.value=Object.fromEntries(categoryAttrs.map(a=>[a.id,copiedAttributes&&a.id in copiedAttributes?copiedAttributes[a.id]:inputValue(saved.get(a.id))]));images.value=photoList;relations.value=relationList.map(r=>({related_product_id:String(r.related_product_id),type:r.type,sort_order:String(r.sort_order)}));groups.value=groupList;prepareGroupDraft(product,groupList);const related=relationList.map(relation=>relation.related_product);candidates.value=[...related,...candidateList.filter(item=>!related.some(savedProduct=>savedProduct.id===item.id))]}
async function open(product:Product|null=null){error.value='';success.value='';copiedFromProduct.value=null;copiedNameError.value='';editing.value=product;resetStep();form.value=product?toPayload(product):emptyProduct();manuallyEditedSlug.value=Boolean(product);opened.value=true;attributes.value=[];attributeGroups.value=[];attributeValues.value={};images.value=[];relations.value=[];groups.value=[];groupProducts.value=[];selectedGroupId.value='';groupForm.value={name:'',code:'',axis_attribute_ids:[],product_ids:[]};groupErrors.value={};groupSearch.value='';if(product)try{await loadDetails(product)}catch(reason){error.value=reason instanceof Error?reason.message:'Не удалось загрузить карточку.'}}
async function cloneProduct(product:Product){await open(null);copiedFromProduct.value=product;form.value={...toPayload(product),name:'',slug:'',article_number:null,barcode:null,is_active:false};manuallyEditedSlug.value=false;try{const [attrs,categoryAttributeGroups,values]=await Promise.all([getCategoryAttributes(product.category_id),getCategoryAttributeGroups(product.category_id),getProductAttributeValues(product.id)]);attributes.value=attrs;attributeGroups.value=categoryAttributeGroups;const saved=new Map(values.map(v=>[v.attribute_id,v.value]));attributeValues.value=Object.fromEntries(attrs.map(a=>[a.id,inputValue(saved.get(a.id))]));success.value='Данные и характеристики скопированы без фотографий. Укажите отличающееся название — URL сформируется из названия, а SKU будет назначен автоматически.'}catch{error.value='Основные данные скопированы, но характеристики исходного товара загрузить не удалось.'}}
function close(){if(!saving.value){opened.value=false;editing.value=null}}
function syncProductThumbnail(){
  if(!editing.value)return
  const primary=images.value.find(image=>image.is_primary)??images.value[0]??null
  const primary_image=primary?{id:primary.id,url:primary.url,alt:primary.alt}:null
  const productId=editing.value.id
  editing.value={...editing.value,primary_image}
  products.value=products.value.map(product=>product.id===productId?{...product,primary_image}:product)
}
async function saveMain(){if(saving.value)return;copiedNameError.value='';if(copiedFromProduct.value&&normalizedProductName(form.value.name)===normalizedProductName(copiedFromProduct.value.name)){copiedNameError.value=`Название должно отличаться от исходного товара «${copiedFromProduct.value.name}».`;return}const copiedAttributes=copiedFromProduct.value?copyAttributeDrafts(attributeValues.value):null;saving.value=true;error.value='';try{const saved=await saveProduct(editing.value?.id??null,{...form.value,is_active:editing.value?form.value.is_active:false,article_number:form.value.article_number||null,barcode:form.value.barcode||null,old_price:form.value.old_price||null});editing.value=saved;form.value=toPayload(saved);await load();await loadDetails(saved,copiedAttributes);success.value='Основные и коммерческие данные сохранены.';activeStep.value='attributes'}catch(reason){error.value=reason instanceof Error?reason.message:'Не удалось сохранить товар.'}finally{saving.value=false}}
async function saveAttributes(){if(!editing.value)return;saving.value=true;try{await saveProductAttributeValues(editing.value.id,{attributes:attributes.value.filter(a=>hasValue(attributeValues.value[a.id])).map(a=>({attribute_id:a.id,value:typed(a,attributeValues.value[a.id])}))});success.value='Характеристики сохранены.';activeStep.value='images'}catch(reason){error.value=reason instanceof Error?reason.message:'Не удалось сохранить характеристики.'}finally{saving.value=false}}
function selectFile(event:Event){selectedFile.value=(event.target as HTMLInputElement).files?.[0]??null}
function chooseFile(){imageInput.value?.click()}
async function upload(){if(!editing.value||!selectedFile.value||saving.value)return;saving.value=true;error.value='';try{const image=await uploadProductImage(editing.value.id,selectedFile.value,{sort_order:images.value.length});images.value.push(image);syncProductThumbnail();selectedFile.value=null;if(imageInput.value)imageInput.value.value='';success.value=`Фотография ${image.alt} загружена.`}catch(reason){error.value=reason instanceof Error?reason.message:'Не удалось загрузить фотографию.'}finally{saving.value=false}}
async function saveImageOrder(reordered:ProductImage[], status:string){if(!editing.value||saving.value)return;saving.value=true;try{for(const [position,image] of reordered.entries())await updateProductImage(editing.value.id,image.id,{sort_order:position,is_primary:position===0});images.value=await getAllProductImages(editing.value.id);syncProductThumbnail();imageStatus.value=status}catch(reason){error.value=reason instanceof Error?reason.message:'Не удалось изменить порядок.'}finally{draggedImageId.value=null;draggedOverImageId.value=null;saving.value=false}}
async function reorderImage(index:number,direction:-1|1){const target=index+direction;if(target<0||target>=images.value.length)return;const reordered=[...images.value];[reordered[index],reordered[target]]=[reordered[target],reordered[index]];await saveImageOrder(reordered,`Порядок сохранён. Изображение на позиции ${target+1}.`)}
function startImageDrag(image:ProductImage,event:DragEvent){if(!canManage.value||saving.value)return;draggedImageId.value=image.id;event.dataTransfer?.setData('text/plain',String(image.id));if(event.dataTransfer)event.dataTransfer.effectAllowed='move'}
function markImageDropTarget(image:ProductImage){if(draggedImageId.value!==null&&draggedImageId.value!==image.id)draggedOverImageId.value=image.id}
function endImageDrag(){draggedImageId.value=null;draggedOverImageId.value=null}
async function dropImage(targetIndex:number){const sourceIndex=images.value.findIndex(image=>image.id===draggedImageId.value);if(sourceIndex<0||sourceIndex===targetIndex){endImageDrag();return}const reordered=[...images.value];const [draggedImage]=reordered.splice(sourceIndex,1);reordered.splice(targetIndex,0,draggedImage);await saveImageOrder(reordered,`Порядок сохранён. Изображение на позиции ${targetIndex+1}.`)}
async function removeImage(){if(!editing.value||!imageDeleting.value||saving.value)return;saving.value=true;confirmError.value='';try{await deleteProductImage(editing.value.id,imageDeleting.value.id);imageDeleting.value=null;images.value=await getAllProductImages(editing.value.id);syncProductThumbnail()}catch(reason){confirmError.value=reason instanceof Error?reason.message:'Не удалось удалить фотографию.'}finally{saving.value=false}}
function selectGroup(id:string){fillGroup(groups.value.find(g=>String(g.id)===id)??null,editing.value!.id)}
async function searchGroupProducts(){if(!editing.value||saving.value)return;saving.value=true;error.value='';try{const found=(await getProducts({search:groupSearch.value||undefined,category_id:editing.value.category_id,brand_id:editing.value.brand_id??undefined,page:1,perPage:25})).data;const selected=groupProducts.value.filter(product=>groupForm.value.product_ids.includes(product.id));groupProducts.value=[...selected,...found.filter(product=>!selected.some(item=>item.id===product.id))]}catch(reason){error.value=reason instanceof Error?reason.message:'Не удалось найти товары.'}finally{saving.value=false}}
async function saveGroup(){if(!editing.value||saving.value)return;groupErrors.value={};if(!groupForm.value.axis_attribute_ids.length){groupErrors.value.axis_attribute_ids=['Выберите хотя бы одну характеристику.'];return}if(groupForm.value.product_ids.length<2){groupErrors.value.product_ids=['Добавьте минимум два товара.'];return}saving.value=true;try{const saved=await saveProductGroup(selectedGroupId.value?Number(selectedGroupId.value):null,groupForm.value);groups.value=await getAllProductGroups();fillGroup(saved,editing.value.id);copiedFromProduct.value=null;success.value='Группа вариантов сохранена.';activeStep.value='review'}catch(reason){if(reason instanceof ProductGroupRequestError)groupErrors.value=reason.details;error.value=reason instanceof Error?reason.message:'Не удалось сохранить группу.'}finally{saving.value=false}}
function addRelation(){const id=selectableCandidates.value[0]?.value;if(id)relations.value.push({related_product_id:id,type:'related',sort_order:String(relations.value.length)})}
async function searchRelations(){if(!editing.value||saving.value)return;saving.value=true;error.value='';try{const found=await getRelationCandidates(editing.value.id,relationSearch.value,20);const selected=candidates.value.filter(product=>relations.value.some(row=>Number(row.related_product_id)===product.id));candidates.value=[...selected,...found.filter(product=>!selected.some(item=>item.id===product.id))]}catch(reason){error.value=reason instanceof Error?reason.message:'Не удалось найти товары.'}finally{saving.value=false}}
async function saveRelations(){if(!editing.value||saving.value)return;relationErrors.value={};saving.value=true;try{const saved=await saveProductRelations(editing.value.id,{relations:relations.value.map(r=>({related_product_id:Number(r.related_product_id),type:r.type,sort_order:Number(r.sort_order)}))});relations.value=saved.map(r=>({related_product_id:String(r.related_product_id),type:r.type,sort_order:String(r.sort_order)}));success.value='Рекомендации сохранены.'}catch(reason){if(reason instanceof ProductRelationRequestError)Object.entries(reason.details).forEach(([key,messages])=>{const m=key.match(/^relations\.(\d+)/);if(m)relationErrors.value[Number(m[1])]=messages[0]});error.value=reason instanceof Error?reason.message:'Не удалось сохранить рекомендации.'}finally{saving.value=false}}
async function removeProduct(){if(!deleting.value||saving.value)return;saving.value=true;confirmError.value='';try{await deleteProduct(deleting.value.id);deleting.value=null;await load()}catch(reason){confirmError.value=reason instanceof Error?reason.message:'Не удалось удалить товар.'}finally{saving.value=false}}
async function ungroup(){if(!selectedGroupId.value||saving.value)return;saving.value=true;confirmError.value='';try{await deleteProductGroup(Number(selectedGroupId.value));groups.value=await getAllProductGroups();fillGroup(null,editing.value!.id);groupDeleting.value=false;success.value='Группа удалена.'}catch(reason){confirmError.value=reason instanceof Error?reason.message:'Не удалось удалить группу.'}finally{saving.value=false}}
async function publish(){if(!editing.value)return;saving.value=true;error.value='';try{const saved=await saveProduct(editing.value.id,{...form.value,is_active:true});editing.value=saved;form.value=toPayload(saved);success.value='Товар опубликован.';await load()}catch(reason){error.value=reason instanceof Error?reason.message:'Не удалось опубликовать товар.'}finally{saving.value=false}}
async function hideProduct(){if(!editing.value)return;saving.value=true;error.value='';try{const saved=await saveProduct(editing.value.id,{...form.value,is_active:false});editing.value=saved;form.value=toPayload(saved);success.value='Товар скрыт и перемещён в черновики.';await load()}catch(reason){error.value=reason instanceof Error?reason.message:'Не удалось скрыть товар.'}finally{saving.value=false}}
let filterTimer: ReturnType<typeof setTimeout> | null = null
let previousFilterSearch = filters.value.search
watch(filters,(next)=>{
  if(filterTimer)clearTimeout(filterTimer)
  const delay=next.search!==previousFilterSearch?350:0
  previousFilterSearch=next.search
  filterTimer=setTimeout(()=>{filterTimer=null;void load(1)},delay)
},{deep:true})
onBeforeUnmount(()=>{if(filterTimer)clearTimeout(filterTimer)})
onMounted(load)
return { products, pagination, error, loading, categories, brands, candidates, groupProducts, opened, saving, editing, deleting, activeStep, steps, success, confirmError, filterResultStatus, exportStatus, exporting, importOpened, priceStatusImportOpened, groupImportOpened, productCountUnavailable, filters, sort, direction, form, manuallyEditedSlug, copiedFromProduct, copiedNameError, attributes, attributeGroups, attributeValues, requiredIds, images, draggedImageId, draggedOverImageId, selectedFile, imageInput, imageDeleting, imageStatus, groups, selectedGroupId, groupErrors, groupForm, groupSearch, groupDeleting, relations, relationErrors, relationSearch, canManage, canManageImports, sortLabels, sortStatus, copiedFromName, units, relationTypes, activityOptions, saleOptions, categoryOptions, brandOptions, filterCategoryOptions, filterBrandOptions, attributeSections, selectedCategory, selectedBrand, groupOptions, currentGroup, selectableCandidates, hasActiveFilters, productCountLabel, relationOptions, ariaSort, formatDate, isProductNameTruncated, productNamePreview, slugify, normalizedName, emptyProduct, toPayload, inputValue, attributeLabel, displayAttributeValue, hasValue, typed, load, resetFilters, changeSort, exportFilteredProducts, enabled, fillGroup, prepareGroupDraft, copyAttributeDrafts, loadDetails, open, cloneProduct, close, syncProductThumbnail, saveMain, saveAttributes, selectFile, chooseFile, upload, reorderImage, startImageDrag, markImageDropTarget, endImageDrag, dropImage, removeImage, selectGroup, searchGroupProducts, saveGroup, addRelation, searchRelations, saveRelations, removeProduct, ungroup, publish, hideProduct }
}
