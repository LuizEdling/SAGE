"""O texto mostrado ao usuário não pode trazer rótulos, tags nem JSON do prompt.

Rodar de dentro do contêiner do agente:
    python -m unittest discover -s tests -t .
"""

import unittest

from app.llm import _remover_vazamentos


class RemoverVazamentosTest(unittest.TestCase):
    def test_linha_de_rotulo_com_json_some_inteira(self):
        texto = (
            "Verifiquei a conta e ela está inativa.\n\n"
            'ALTERACAO: Ativar ou desativar a conta de um usuário com os valores {"usuario_login": "pedro.costa", "ativo": true}'
        )
        self.assertEqual(_remover_vazamentos(texto), "Verifiquei a conta e ela está inativa.")

    def test_rotulo_sem_json_mantem_a_explicacao(self):
        texto = "ALTERAÇÃO DISPONÍVEL: Ativar a conta do Pedro Costa. Basta clicar em Autorizar."
        self.assertEqual(_remover_vazamentos(texto), "Ativar a conta do Pedro Costa. Basta clicar em Autorizar.")

    def test_rotulo_em_negrito(self):
        self.assertEqual(_remover_vazamentos("**ALTERAÇÃO DISPONÍVEL:** ativar a conta."), "ativar a conta.")

    def test_json_no_meio_da_frase(self):
        texto = 'Vou usar os valores {"ativo": true} para ativar a conta.'
        self.assertNotIn("{", _remover_vazamentos(texto))

    def test_tags_internas(self):
        texto = "<contexto>Chamados</contexto>\nO prazo é de 7 dias.\n<dados_do_sistema>"
        self.assertEqual(_remover_vazamentos(texto), "Chamados\nO prazo é de 7 dias.")

    def test_resposta_normal_fica_intacta(self):
        texto = (
            "Entendi. Para ativar a notificação, clique em **Autorizar** logo abaixo.\n\n"
            "Se preferir não alterar, clique em Negar."
        )
        self.assertEqual(_remover_vazamentos(texto), texto)

    def test_chaves_em_texto_comum_sem_json(self):
        self.assertEqual(_remover_vazamentos("Use o campo {nome} da ficha."), "Use o campo {nome} da ficha.")


if __name__ == "__main__":
    unittest.main()
